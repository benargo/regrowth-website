<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\SyncMember;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\RowResultData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Enums\RankSource;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\Ban;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Carbon\CarbonInterface;
use Database\Factories\CharacterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class SyncMemberTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private const string GUID = 'Player-5826-0A1B2C3D';

    private GameVersion $version;

    /** @var Collection<int, GuildRank> */
    private Collection $ranks;

    private PlayableClass $warlock;

    private PlayableRace $undead;

    private CarbonInterface $syncStartedAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = GameVersion::factory()->withSurnames()->fetchableRoster()->create(['guild_name' => 'Regrowth']);

        foreach (range(0, 9) as $sortOrder) {
            GuildRank::factory()->for($this->version)->create(['sort_order' => $sortOrder]);
        }

        $this->ranks = $this->version->guildRanks()->get()->keyBy('sort_order');
        $this->warlock = PlayableClass::factory()->create(['name' => 'Warlock']);
        $this->undead = PlayableRace::factory()->create(['name' => 'Undead']);
        $this->version->playableClasses()->attach($this->warlock);
        $this->version->playableRaces()->attach($this->undead);
        $this->syncStartedAt = Carbon::parse('2026-11-14 12:00:00', 'UTC');
    }

    #[Test]
    public function it_updates_a_known_character_without_calling_blizzard(): void
    {
        Saloon::fake([]);
        $character = $this->mira()->withGrmGuid(self::GUID)->create(['level' => 50]);

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Processed, RowReason::Updated]], $results);
        $this->assertSame($character->id, $results[0]->characterId);
        $this->assertSame(self::GUID, $results[0]->guid);
        $this->assertSame('Mira Ashvale', $results[0]->name);
        $character->refresh();
        $this->assertSame(60, $character->level);
        $this->assertSame($this->warlock->id, $character->playable_class_id);
        $this->assertSame($this->undead->id, $character->playable_race_id);
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        $this->assertTrue($character->guildMemberships()->whereDate('joined_on', '2026-08-02')->exists());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_attaches_the_guid_to_a_name_match_without_calling_blizzard(): void
    {
        Saloon::fake([]);
        $character = $this->mira()->create();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Processed, RowReason::Updated]], $results);
        $this->assertSame(self::GUID, $character->refresh()->grm_guid);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_assigns_the_rank_by_rank_index(): void
    {
        Saloon::fake([]);
        $character = $this->mira()->withGrmGuid(self::GUID)->create();
        $rankNames = $this->version->guildRanks()->orderBy('sort_order')->pluck('name')->all();

        $this->sync($this->member(['rankIndex' => 1]));

        $this->assertSame($this->ranks->get(1)->id, $character->refresh()->rank_id);
        $this->assertSame($rankNames, $this->version->guildRanks()->orderBy('sort_order')->pluck('name')->all());
    }

    #[Test]
    public function it_skips_below_the_minimum_level_without_calling_blizzard(): void
    {
        Saloon::fake([]);
        $character = $this->mira()->create(['level' => 5]);

        $results = $this->sync($this->member(['level' => 9]));

        $this->assertResults([[RowStatus::Skipped, RowReason::BelowMinimumLevel]], $results);
        $character->refresh();
        $this->assertNull($character->grm_guid);
        $this->assertSame(5, $character->level);
        $this->assertNull($character->rank_id);
        $this->assertSame(1, Character::count());
        Saloon::assertNothingSent();
    }

    // ==================== new characters ====================

    #[Test]
    public function it_creates_a_new_character_with_one_profile_call_when_blizzard_lists_the_guild(): void
    {
        $this->mockGetCharacterProfile(responseData: ['id' => 4242, 'guild' => $this->guild('Regrowth')]);
        $this->applyBlizzardMocks();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Processed, RowReason::Created]], $results);
        $this->assertSame(4242, $results[0]->characterId);
        $character = Character::findOrFail(4242);
        $this->assertSame(self::GUID, $character->grm_guid);
        $this->assertSame('Mira', $character->name);
        $this->assertSame('Ashvale', $character->surname);
        $this->assertSame($this->version->id, $character->game_version_id);
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        Saloon::mockClient()->assertSentCount(1, GetCharacterProfileRequest::class);
        Saloon::assertSent(fn (Request $request): bool => str_ends_with($request->resolveEndpoint(), '/mira-ashvale'));
    }

    #[Test]
    public function it_creates_nothing_when_blizzard_lists_another_guild(): void
    {
        $this->mockGetCharacterProfile(responseData: ['id' => 4242, 'guild' => $this->guild('Other Guild')]);
        $this->applyBlizzardMocks();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Warning, RowReason::NotConfirmedInGuild]], $results);
        $this->assertNull($results[0]->characterId);
        $this->assertStringContainsString('not yet confirmed in guild by Blizzard', $results[0]->message);
        $this->assertStringContainsString('Other Guild', $results[0]->message);
        $this->assertSame(0, Character::count());
    }

    #[Test]
    public function it_creates_nothing_when_blizzard_lists_no_guild(): void
    {
        $this->mockGetCharacterProfile(responseData: ['id' => 4242]);
        $this->applyBlizzardMocks();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Warning, RowReason::NotConfirmedInGuild]], $results);
        $this->assertStringContainsString('no guild', $results[0]->message);
        $this->assertSame(0, Character::count());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_a_character_blizzard_cannot_find_as_not_confirmed(): void
    {
        $this->mockGetCharacterProfile();
        $this->mockNotFoundResponse(GetCharacterProfileRequest::class);
        $this->applyBlizzardMocks();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Warning, RowReason::NotConfirmedInGuild]], $results);
        $this->assertStringContainsString('Character not found', $results[0]->message);
        $this->assertSame(0, Character::count());
    }

    #[Test]
    public function it_never_caches_a_negative_guild_answer(): void
    {
        $guilds = ['Other Guild', 'Regrowth'];
        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE),
            GetCharacterProfileRequest::class => function () use (&$guilds): MockResponse {
                return MockResponse::make(body: [
                    ...$this->makeCharacterProfileResponse(),
                    'id' => 4242,
                    'guild' => $this->guild(array_shift($guilds)),
                ]);
            },
        ]);

        $first = $this->sync($this->member());
        $second = $this->sync($this->member());

        $this->assertResults([[RowStatus::Warning, RowReason::NotConfirmedInGuild]], $first);
        $this->assertResults([[RowStatus::Processed, RowReason::Created]], $second);
        $this->assertTrue(Character::whereKey(4242)->exists());
        Saloon::mockClient()->assertSentCount(2, GetCharacterProfileRequest::class);
    }

    // ==================== renames and clashes ====================

    #[Test]
    public function it_records_a_rename_against_an_existing_guid(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->create();

        $results = $this->sync($this->member(['name' => 'Liané Moonfall']));

        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $results);
        $this->assertSame('Mira Ashvale → Liané Moonfall', $results[0]->message);
        $this->assertSame('Liané Moonfall', $character->refresh()->full_name);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_a_name_clash_and_continues(): void
    {
        $mira = $this->mira()->withGrmGuid(self::GUID)->create(['level' => 50]);
        $liane = Character::factory()->forGameVersion($this->version)->withSurname('Moonfall')->withGrmGuid('Player-5826-0A1B2C3F')
            ->create(['name' => 'Liané', 'level' => 50]);

        $clash = $this->sync($this->member(['name' => 'Liané Moonfall']));
        $next = $this->sync($this->member(['guid' => 'Player-5826-0A1B2C3F', 'name' => 'Liané Moonfall']));

        $this->assertResults([[RowStatus::Failed, RowReason::NameClash]], $clash);
        $this->assertSame($mira->id, $clash[0]->characterId);
        $mira->refresh();
        $this->assertSame('Mira Ashvale', $mira->full_name);
        $this->assertSame(50, $mira->level);
        $this->assertNull($mira->rank_id);
        $this->assertSame(0, $mira->guildMemberships()->count());
        $this->assertResults([[RowStatus::Processed, RowReason::Updated]], $next);
        $this->assertSame(60, $liane->refresh()->level);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_a_clash_in_a_rename_chain(): void
    {
        $brin = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-AAAA')->create(['name' => 'Brin']);
        $brinn = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-BBBB')->create(['name' => 'Brinn']);

        $first = $this->sync($this->member(['guid' => 'Player-1-BBBB', 'name' => 'Brin']));
        $second = $this->sync($this->member(['guid' => 'Player-1-AAAA', 'name' => 'Brinora']));

        $this->assertResults([[RowStatus::Failed, RowReason::NameClash]], $first);
        $this->assertSame('Brinn', $brinn->refresh()->name);
        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $second);
        $this->assertSame('Brin → Brinora', $second[0]->message);
        $this->assertSame('Brinora', $brin->refresh()->name);
    }

    #[Test]
    public function it_keeps_the_name_for_a_placeholder_rename(): void
    {
        $this->version->update(['uses_surnames' => false]);
        $character = $this->oswin()->create(['level' => 50]);

        $results = $this->sync($this->member(['name' => 'PlayerKXQBNM-Thunderstrike']));

        $this->assertResults([
            [RowStatus::Processed, RowReason::Updated],
            [RowStatus::Warning, RowReason::PlaceholderName],
        ], $results);
        $this->assertSame('Oswin → PlayerKXQBNM', $results[1]->message);
        $character->refresh();
        $this->assertSame('Oswin', $character->name);
        $this->assertSame(60, $character->level);
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
    }

    #[Test]
    public function it_keeps_the_name_for_a_placeholder_rename_on_a_surname_version(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->create();

        $results = $this->sync($this->member(['name' => 'PlayerKXQBNM']));

        $this->assertResults([
            [RowStatus::Processed, RowReason::Updated],
            [RowStatus::Warning, RowReason::PlaceholderName],
        ], $results);
        $this->assertSame('Mira Ashvale → PlayerKXQBNM', $results[1]->message);
        $character->refresh();
        $this->assertSame('Mira', $character->name);
        $this->assertSame('Ashvale', $character->surname);
    }

    #[Test]
    public function it_applies_a_real_name_after_a_placeholder(): void
    {
        $this->version->update(['uses_surnames' => false]);
        $character = $this->oswin()->create();
        $this->sync($this->member(['name' => 'PlayerKXQBNM']));

        $results = $this->sync($this->member(['name' => 'Oswyn']));

        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $results);
        $this->assertSame('Oswin → Oswyn', $results[0]->message);
        $this->assertSame('Oswyn', $character->refresh()->name);
    }

    #[Test]
    public function it_does_not_treat_a_lookalike_as_a_placeholder(): void
    {
        $this->version->update(['uses_surnames' => false]);
        $character = $this->oswin()->create();

        $first = $this->sync($this->member(['name' => 'Playerone']));
        $second = $this->sync($this->member(['name' => 'PlayerABCDEFG']));

        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $first);
        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $second);
        $this->assertSame('PlayerABCDEFG', $character->refresh()->name);
    }

    #[Test]
    public function it_marks_a_returning_character_as_rejoined(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->leftGuild(Carbon::parse('2026-10-01'))->create();

        $results = $this->sync($this->member());

        $this->assertResults([[RowStatus::Processed, RowReason::Rejoined]], $results);
        $this->assertNull($character->refresh()->left_guild_at);
    }

    // ==================== warnings ====================

    #[Test]
    public function it_warns_on_an_unknown_class_race_or_rank(): void
    {
        $rank = $this->ranks->get(2);
        $character = $this->mira()->withGrmGuid(self::GUID)->withPlayableClass($this->warlock)->withPlayableRace($this->undead)
            ->create(['rank_id' => $rank->id, 'level' => 50]);

        $results = $this->sync($this->member(['class' => 'DEATHKNIGHT', 'race' => 'Goblin', 'rankIndex' => 12]));

        $this->assertResults([
            [RowStatus::Processed, RowReason::Updated],
            [RowStatus::Warning, RowReason::UnknownClass],
            [RowStatus::Warning, RowReason::UnknownRace],
            [RowStatus::Warning, RowReason::UnknownRank],
        ], $results);
        $this->assertSame(['DEATHKNIGHT', 'Goblin', '12'], array_map(fn (RowResultData $result): ?string => $result->message, array_slice($results, 1)));
        $character->refresh();
        $this->assertSame(60, $character->level);
        $this->assertSame($this->warlock->id, $character->playable_class_id);
        $this->assertSame($this->undead->id, $character->playable_race_id);
        $this->assertSame($rank->id, $character->rank_id);
    }

    // ==================== grm evidence ====================

    #[Test]
    public function it_writes_rank_and_grm_evidence_from_the_raw_epoch(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->create();
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');

        $this->sync($this->member(), $evidenceAt);

        $character->refresh();
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        $this->assertTrue($evidenceAt->equalTo($character->rank_observed_at));
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_writes_grm_rank_over_newer_blizzard_evidence(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $character = $this->mira()->withGrmGuid(self::GUID)->rankObserved($evidenceAt->copy()->addDay())
            ->create(['rank_id' => $this->ranks->get(5)->id]);

        $this->sync($this->member(), $evidenceAt);

        $character->refresh();
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        $this->assertTrue($evidenceAt->equalTo($character->rank_observed_at));
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_leaves_rank_observed_at_null_for_an_epoch_of_zero(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->rankObserved(Carbon::parse('2026-11-01'))
            ->create(['rank_id' => $this->ranks->get(5)->id]);

        $this->sync($this->member());

        $character->refresh();
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        $this->assertNull($character->rank_observed_at);
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_writes_level_with_the_sync_start_time_and_never_lowers_it(): void
    {
        $character = $this->mira()->withGrmGuid(self::GUID)->create(['level' => 62, 'level_observed_at' => Carbon::parse('2026-11-01')]);

        $this->sync($this->member());

        $character->refresh();
        $this->assertSame(62, $character->level);
        $this->assertTrue($this->syncStartedAt->equalTo($character->level_observed_at));
    }

    #[Test]
    public function it_clears_left_guild_at_for_an_applied_member_row(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $character = $this->mira()->withGrmGuid(self::GUID)->leftGuild($evidenceAt->copy()->addDay())->create();

        $results = $this->sync($this->member(), $evidenceAt);

        $this->assertResults([[RowStatus::Processed, RowReason::Rejoined]], $results);
        $this->assertNull($character->refresh()->left_guild_at);
    }

    // ==================== retry rows ====================

    #[Test]
    public function it_keeps_newer_blizzard_rank_evidence_for_a_retry_row(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $observedAt = $evidenceAt->copy()->addDay()->setTimezone(config('app.timezone'));
        $character = $this->mira()->withGrmGuid(self::GUID)->rankObserved($observedAt)
            ->create(['rank_id' => $this->ranks->get(5)->id]);

        $this->sync($this->member(), $evidenceAt, isRetry: true);

        $character->refresh();
        $this->assertSame($this->ranks->get(5)->id, $character->rank_id);
        $this->assertTrue($observedAt->equalTo($character->rank_observed_at));
        $this->assertSame(RankSource::Blizzard, $character->rank_source);
    }

    #[Test]
    public function it_writes_rank_for_a_retry_row_with_newer_evidence(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $character = $this->mira()->withGrmGuid(self::GUID)->rankObserved($evidenceAt->copy()->subDay())
            ->create(['rank_id' => $this->ranks->get(5)->id]);

        $this->sync($this->member(), $evidenceAt, isRetry: true);

        $character->refresh();
        $this->assertSame($this->ranks->get(3)->id, $character->rank_id);
        $this->assertTrue($evidenceAt->equalTo($character->rank_observed_at));
        $this->assertSame(RankSource::Grm, $character->rank_source);
    }

    #[Test]
    public function it_keeps_level_evidence_newer_than_the_retry_row(): void
    {
        $observedAt = $this->syncStartedAt->copy()->addHour()->setTimezone(config('app.timezone'));
        $character = $this->mira()->withGrmGuid(self::GUID)->create(['level' => 50, 'level_observed_at' => $observedAt]);

        $this->sync($this->member(), isRetry: true);

        $character->refresh();
        $this->assertSame(50, $character->level);
        $this->assertTrue($observedAt->equalTo($character->level_observed_at));
    }

    #[Test]
    public function it_does_not_clear_a_later_departure_for_a_retry_row(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $character = $this->mira()->withGrmGuid(self::GUID)->leftGuild($evidenceAt->copy()->addDay())->create();

        $results = $this->sync($this->member(), $evidenceAt, isRetry: true);

        $this->assertResults([[RowStatus::Processed, RowReason::Updated]], $results);
        $this->assertNotNull($character->refresh()->left_guild_at);
    }

    #[Test]
    public function it_still_writes_class_race_and_name_for_a_retry_row(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1785657600, 'UTC');
        $character = $this->mira()->withGrmGuid(self::GUID)->rankObserved($evidenceAt->copy()->addDay())
            ->create(['level_observed_at' => $this->syncStartedAt->copy()->addHour()->setTimezone(config('app.timezone'))]);

        $results = $this->sync($this->member(['name' => 'Liané Moonfall']), $evidenceAt, isRetry: true);

        $this->assertResults([[RowStatus::Processed, RowReason::Renamed]], $results);
        $character->refresh();
        $this->assertSame('Liané Moonfall', $character->full_name);
        $this->assertSame($this->warlock->id, $character->playable_class_id);
        $this->assertSame($this->undead->id, $character->playable_race_id);
    }

    #[Test]
    public function it_links_the_members_ban(): void
    {
        Saloon::fake([]);
        $character = $this->mira()->withGrmGuid(self::GUID)->forUser()->create();
        $ban = Ban::factory()->create(['game_version_id' => $this->version->id, 'grm_guid' => self::GUID]);

        $this->sync($this->member());

        $ban->refresh();
        $this->assertSame($character->id, $ban->character_id);
        $this->assertSame($character->user_id, $ban->user_id);
    }

    // ==================== helpers ====================

    /**
     * Run the action for the given member against the test version and ranks.
     *
     * @return list<RowResultData>
     */
    private function sync(MemberData $member, ?CarbonInterface $evidenceAt = null, bool $isRetry = false, ?CarbonInterface $syncStartedAt = null): array
    {
        return SyncMember::run($this->version, $member, $this->ranks, $syncStartedAt ?? $this->syncStartedAt, $evidenceAt, $isRetry);
    }

    /**
     * Build the fixture's first member (Mira Ashvale) with the given overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function member(array $overrides = []): MemberData
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/GuildRosterManager/forever-roster.json')), true);

        return MemberData::from([...$fixture['members'][0], ...$overrides]);
    }

    /**
     * Build a Blizzard guild link with the given name.
     *
     * @return array{key: array{href: string}, name: string, id: int}
     */
    private function guild(string $name): array
    {
        return ['key' => ['href' => 'https://example.test/guild/1'], 'name' => $name, 'id' => 1];
    }

    /**
     * A character factory for Mira Ashvale in the test version.
     */
    private function mira(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withSurname('Ashvale')->state(['name' => 'Mira']);
    }

    /**
     * A character factory for Oswin, holding the fixture member's GUID, in the test version.
     */
    private function oswin(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withGrmGuid(self::GUID)->state(['name' => 'Oswin']);
    }

    /**
     * Assert the results' statuses and reasons, in order.
     *
     * @param  list<array{0: RowStatus, 1: RowReason}>  $expected
     * @param  list<RowResultData>  $results
     */
    private function assertResults(array $expected, array $results): void
    {
        $this->assertSame(
            $expected,
            array_map(fn (RowResultData $result): array => [$result->status, $result->reason], $results),
        );
    }
}
