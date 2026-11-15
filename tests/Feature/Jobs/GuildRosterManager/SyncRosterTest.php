<?php

namespace Tests\Feature\Jobs\GuildRosterManager;

use App\Actions\GuildRosterManager\SyncMember;
use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\RowResultData;
use App\Data\GuildRosterManager\SyncChangesData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\RankSource;
use App\Events\DesktopClient\SyncCommitted;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Jobs\FetchGuildRoster;
use App\Jobs\GuildRosterManager\SyncRoster;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Carbon\CarbonInterface;
use Database\Factories\CharacterFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class SyncRosterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private const string MIRA = 'Player-5826-0A1B2C3D';

    private const string PAN = 'Player-5826-0A1B2C3E';

    private const string LIANE = 'Player-5826-0A1B2C3F';

    private const string ASHDOWN = 'Player-5826-0A1B2C40';

    private const int EPOCH = 1785657600;

    private GameVersion $version;

    /** @var Collection<int, GuildRank> */
    private Collection $ranks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-14 12:00:00'));
        Saloon::fake([]);
        Bus::fake([FetchGuildRoster::class]);
        Notification::fake();
        Event::fake([SyncCommitted::class]);

        $this->version = GameVersion::factory()->withSurnames()->fetchableRoster()->create(['guild_name' => 'Regrowth']);

        foreach (range(0, 9) as $sortOrder) {
            GuildRank::factory()->for($this->version)->create(['sort_order' => $sortOrder]);
        }

        $this->ranks = $this->version->guildRanks()->get()->keyBy('sort_order');
        $warlock = PlayableClass::factory()->create(['name' => 'Warlock']);
        $undead = PlayableRace::factory()->create(['name' => 'Undead']);
        $this->version->playableClasses()->attach($warlock);
        $this->version->playableRaces()->attach($undead);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_processes_a_first_sync_and_records_row_outcomes(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create();
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->leaverEntry(self::PAN, ['name' => 'Pan Bakewell']);
        $this->leaverEntry(self::ASHDOWN, ['name' => 'Pan Ashdown']);

        $this->runJob($sync, $this->changes(members: [self::MIRA], leavers: [self::PAN, self::ASHDOWN]));

        $sync->refresh();
        $this->assertSame(UploadOutcome::Processed, $sync->outcome);
        $this->assertTrue(now()->equalTo($sync->processed_at));
        $this->assertSame([2, 1, 0, 0], [$sync->processed_count, $sync->skipped_count, $sync->warning_count, $sync->error_count]);
        $this->assertRows($sync, [
            [self::MIRA, RowStatus::Processed, RowReason::Updated],
            [self::PAN, RowStatus::Processed, RowReason::Left],
            [self::ASHDOWN, RowStatus::Skipped, RowReason::NotTracked],
        ]);
        $this->assertSame($mira->id, $sync->rows()->where('guid', self::MIRA)->value('character_id'));
        $this->assertSame(3, $sync->rows()->where('created_at', now())->where('updated_at', now())->count());
        $this->assertSame(60, $mira->refresh()->level);
        $this->assertNotNull($pan->refresh()->left_guild_at);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_records_no_changes_for_empty_changes(): void
    {
        $sync = $this->commitSync(1);

        $this->runJob($sync, $this->changes());

        $sync->refresh();
        $this->assertSame(UploadOutcome::NoChanges, $sync->outcome);
        $this->assertTrue(now()->equalTo($sync->processed_at));
        $this->assertSame(0, $sync->rows()->count());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_processes_only_the_keys_in_the_changes(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create(['level' => 50]);
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::PAN, ['name' => 'Pan Bakewell']);
        $this->memberEntry(self::LIANE, ['name' => 'Liané Moonfall']);

        $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $this->assertRows($sync, [[self::MIRA, RowStatus::Processed, RowReason::Updated]]);
        $this->assertSame(50, $pan->refresh()->level);
        $this->assertNull($pan->rank_id);
        $this->assertSame(2, Character::count());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_reads_row_data_from_the_roster_state(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 40]);
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA, ['name' => 'Liané Moonfall', 'rankIndex' => 1, 'level' => 58]);

        $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $mira->refresh();
        $this->assertSame('Liané Moonfall', $mira->full_name);
        $this->assertSame($this->ranks->get(1)->id, $mira->rank_id);
        $this->assertSame(58, $mira->level);
    }

    #[Test]
    public function it_processes_departed_members_as_leavers_from_their_leaver_entry(): void
    {
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create();
        $sync = $this->commitSync(1);
        $this->leaverEntry(self::PAN, [
            'name' => 'Pan Bakewell',
            'history' => [
                ['type' => 'joined', 'date' => '2026-08-05'],
                ['type' => 'left', 'date' => '2026-09-01'],
                ['type' => 'joined', 'date' => '2026-09-10'],
                ['type' => 'left', 'date' => '2026-10-01'],
            ],
        ], epoch: 0);

        $this->runJob($sync, $this->changes(leavers: [self::PAN], departed: [self::PAN]));

        $this->assertRows($sync, [[self::PAN, RowStatus::Processed, RowReason::Left]]);
        $this->assertTrue(Carbon::parse('2026-10-01 00:00:00', config('app.timezone'))->equalTo($pan->refresh()->left_guild_at));
    }

    #[Test]
    public function it_passes_the_raw_epoch_as_evidence(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create();
        $liane = $this->liane()->withGrmGuid(self::LIANE)->create();
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::LIANE, ['name' => 'Liané Moonfall'], epoch: 0);

        $this->runJob($sync, $this->changes(members: [self::MIRA, self::LIANE]));

        $this->assertTrue(Carbon::createFromTimestamp(self::EPOCH, 'UTC')->equalTo($mira->refresh()->rank_observed_at));
        $this->assertSame(RankSource::Grm, $mira->rank_source);
        $this->assertNull($liane->refresh()->rank_observed_at);
    }

    #[Test]
    public function it_changes_no_character_for_a_purged_leaver(): void
    {
        $leftAt = Carbon::parse('2026-10-01 00:00:00');
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->leftGuild($leftAt)->create(['level' => 50]);
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);

        $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $pan->refresh();
        $this->assertTrue($leftAt->equalTo($pan->left_guild_at));
        $this->assertSame(50, $pan->level);
        $this->assertSame(0, UploadRow::where('guid', self::PAN)->count());
    }

    // ==================== alt groups ====================

    #[Test]
    public function it_links_changed_alt_groups_from_the_roster_state(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['is_main' => false]);
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create(['is_main' => false]);
        $sync = $this->commitSync(1);
        $this->altGroupEntry('12', 'Mira Ashvale', [self::MIRA, self::PAN]);

        $this->runJob($sync, $this->changes(altGroups: ['12'], previousAltGroups: ['12' => null]));

        $this->assertSame([$pan->id], $mira->linkedCharacters()->pluck('characters.id')->all());
        $this->assertSame([$mira->id], $pan->linkedCharacters()->pluck('characters.id')->all());
        $this->assertTrue($mira->refresh()->is_main);
        $this->assertRows($sync, [[self::MIRA, RowStatus::Processed, RowReason::MainChanged]]);
    }

    #[Test]
    public function it_detaches_characters_using_the_previous_alt_group_payload(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->main()->create();
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create(['is_main' => false]);
        $liane = $this->liane()->withGrmGuid(self::LIANE)->create(['is_main' => false]);
        $this->link($mira, $pan, $liane);
        $sync = $this->commitSync(1);
        $this->altGroupEntry('12', 'Mira Ashvale', [self::MIRA, self::PAN]);

        $this->runJob($sync, $this->changes(altGroups: ['12'], previousAltGroups: [
            '12' => ['id' => '12', 'main' => 'Mira Ashvale', 'members' => [self::MIRA, self::PAN, self::LIANE]],
        ]));

        $this->assertSame([], $liane->linkedCharacters()->pluck('characters.id')->all());
        $this->assertSame([$pan->id], $mira->linkedCharacters()->pluck('characters.id')->all());
        $this->assertSame([$mira->id], $pan->linkedCharacters()->pluck('characters.id')->all());
        $this->assertRows($sync, [[self::LIANE, RowStatus::Processed, RowReason::Detached]]);
    }

    #[Test]
    public function it_links_the_alt_group_of_a_member_created_from_a_retry_row(): void
    {
        $this->fakeGuildProfiles(['Other Guild', 'Regrowth']);
        $pan = $this->panBakewell()->withGrmGuid(self::PAN)->create(['is_main' => false]);
        $first = $this->commitSync(1);
        $this->memberEntry(self::MIRA, ['altGroup' => '12']);
        $this->altGroupEntry('12', 'Mira Ashvale', [self::MIRA, self::PAN]);

        $this->runJob($first, $this->changes(members: [self::MIRA], altGroups: ['12'], previousAltGroups: ['12' => null]));

        $this->assertSame([], $pan->linkedCharacters()->pluck('characters.id')->all());

        $second = $this->commitSync(2);

        $this->runJob($second, $this->changes());

        $this->assertSame([4242], $pan->linkedCharacters()->pluck('characters.id')->all());
        $this->assertTrue(Character::findOrFail(4242)->is_main);
    }

    // ==================== revision order ====================

    #[Test]
    public function it_waits_for_an_earlier_unprocessed_revision(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $this->commitSync(1);
        $sync = $this->commitSync(2);
        $this->memberEntry(self::MIRA, revision: 2);

        $job = $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $job->assertReleased(60);
        $this->assertSame(UploadOutcome::Received, $sync->refresh()->outcome);
        $this->assertSame(50, $mira->refresh()->level);
        $this->assertSame(0, UploadRow::count());
        Event::assertNotDispatched(SyncCommitted::class);
    }

    #[Test]
    public function it_proceeds_past_an_earlier_revision_abandoned_beyond_the_retry_window(): void
    {
        Log::spy();
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $stale = $this->commitSync(1, committedAt: now()->subHours(8));
        $sync = $this->commitSync(2);
        $this->memberEntry(self::MIRA, revision: 2);

        $job = $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $job->assertNotReleased();
        $this->assertSame(UploadOutcome::Processed, $sync->refresh()->outcome);
        $this->assertSame(60, $mira->refresh()->level);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, $stale->id)
            && str_contains($message, 'revision 1')
            && str_contains($message, (string) $this->version->id));
    }

    #[Test]
    public function it_still_waits_for_a_recent_unfinished_revision(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $this->commitSync(1, committedAt: now()->subHour());
        $sync = $this->commitSync(2);
        $this->memberEntry(self::MIRA, revision: 2);

        $job = $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $job->assertReleased(60);
        $this->assertSame(UploadOutcome::Received, $sync->refresh()->outcome);
        $this->assertSame(0, UploadRow::count());
    }

    #[Test]
    public function it_does_nothing_for_a_sync_already_processed(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $sync = $this->commitSync(1, outcome: UploadOutcome::Processed);
        $sync->update(['processed_count' => 3, 'skipped_count' => 1, 'warning_count' => 2, 'error_count' => 0]);
        $this->memberEntry(self::MIRA);

        $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $sync->refresh();
        $this->assertSame(UploadOutcome::Processed, $sync->outcome);
        $this->assertSame([3, 1, 2, 0], [$sync->processed_count, $sync->skipped_count, $sync->warning_count, $sync->error_count]);
        $this->assertSame(0, UploadRow::count());
        $this->assertSame(50, Character::first()->level);
        Event::assertNotDispatched(SyncCommitted::class);
    }

    #[Test]
    public function it_ignores_earlier_syncs_that_never_committed_and_other_versions(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $this->commitSync(1, outcome: UploadOutcome::Superseded);
        $this->commitSync(1, outcome: UploadOutcome::Abandoned);
        $this->commitSync(1, outcome: UploadOutcome::Expired);
        Upload::factory()->committed(1)->create();
        $sync = $this->commitSync(2);
        $this->memberEntry(self::MIRA, revision: 2);

        $job = $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $job->assertNotReleased();
        $this->assertSame(UploadOutcome::Processed, $sync->refresh()->outcome);
    }

    #[Test]
    public function it_proceeds_after_an_earlier_failed_revision(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $this->commitSync(1, outcome: UploadOutcome::Failed);
        $sync = $this->commitSync(2);
        $this->memberEntry(self::MIRA, revision: 2);

        $job = $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $job->assertNotReleased();
        $this->assertSame(UploadOutcome::Processed, $sync->refresh()->outcome);
        $this->assertSame(60, $mira->refresh()->level);
    }

    // ==================== retry rows ====================

    #[Test]
    public function it_retries_rows_that_failed_or_warned_last_time(): void
    {
        $mira = $this->mira()->withGrmGuid(self::MIRA)->create(['level' => 50]);
        $earlier = $this->commitSync(1, outcome: UploadOutcome::Failed);
        $this->memberEntry(self::MIRA);
        $this->uploadRow($earlier, self::MIRA, RowStatus::Failed, RowReason::Error);
        $this->uploadRow($earlier, self::PAN, RowStatus::Failed, RowReason::Error);
        $sync = $this->commitSync(2);

        $this->runJob($sync, $this->changes());

        $this->assertSame(UploadOutcome::Processed, $sync->refresh()->outcome);
        $this->assertRows($sync, [[self::MIRA, RowStatus::Processed, RowReason::Updated]]);
        $this->assertSame(60, $mira->refresh()->level);
    }

    #[Test]
    public function it_gates_retry_rows_on_evidence(): void
    {
        $committedAt = Carbon::parse('2026-11-10 12:00:00');
        $rankObservedAt = Carbon::createFromTimestamp(self::EPOCH, 'UTC')->addDay()->setTimezone(config('app.timezone'));
        $levelObservedAt = $committedAt->copy()->addHour();
        $mira = $this->mira()->withGrmGuid(self::MIRA)->rankObserved($rankObservedAt)
            ->create(['rank_id' => $this->ranks->get(5)->id, 'level' => 50, 'level_observed_at' => $levelObservedAt]);
        $earlier = $this->commitSync(1, outcome: UploadOutcome::Processed, committedAt: $committedAt);
        $this->memberEntry(self::MIRA);
        $this->uploadRow($earlier, self::MIRA, RowStatus::Processed, RowReason::Updated);
        $this->uploadRow($earlier, self::MIRA, RowStatus::Warning, RowReason::UnknownRank);
        $sync = $this->commitSync(2);

        $this->runJob($sync, $this->changes());

        $mira->refresh();
        $this->assertRows($sync, [[self::MIRA, RowStatus::Processed, RowReason::Updated]]);
        $this->assertSame($this->ranks->get(5)->id, $mira->rank_id);
        $this->assertSame(RankSource::Blizzard, $mira->rank_source);
        $this->assertTrue($rankObservedAt->equalTo($mira->rank_observed_at));
        $this->assertSame(50, $mira->level);
        $this->assertTrue($levelObservedAt->equalTo($mira->level_observed_at));
    }

    #[Test]
    public function it_treats_a_jobs_primary_result_and_warnings_as_one_outcome(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $this->panBakewell()->withGrmGuid(self::PAN)->create();
        $oldest = $this->commitSync(1, outcome: UploadOutcome::Processed);
        $earlier = $this->commitSync(2, outcome: UploadOutcome::Processed);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::PAN, ['name' => 'Pan Bakewell']);
        $this->uploadRow($oldest, self::PAN, RowStatus::Warning, RowReason::UnknownRank);
        $this->uploadRow($earlier, self::MIRA, RowStatus::Processed, RowReason::Updated);
        $this->uploadRow($earlier, self::MIRA, RowStatus::Warning, RowReason::UnknownRank);
        $this->uploadRow($earlier, self::PAN, RowStatus::Processed, RowReason::Updated);
        $sync = $this->commitSync(3);

        $this->runJob($sync, $this->changes());

        $this->assertRows($sync, [[self::MIRA, RowStatus::Processed, RowReason::Updated]]);
    }

    #[Test]
    public function it_checks_a_member_not_yet_confirmed_in_guild_again_on_the_next_sync(): void
    {
        $guilds = ['Other Guild', 'Regrowth'];
        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE),
            GetCharacterProfileRequest::class => function () use (&$guilds): MockResponse {
                return MockResponse::make(body: [
                    ...$this->makeCharacterProfileResponse(),
                    'id' => 4242,
                    'guild' => ['key' => ['href' => 'https://example.test/guild/1'], 'name' => array_shift($guilds), 'id' => 1],
                ]);
            },
        ]);
        $first = $this->commitSync(1);
        $this->memberEntry(self::MIRA);

        $this->runJob($first, $this->changes(members: [self::MIRA]));

        $this->assertRows($first, [[self::MIRA, RowStatus::Warning, RowReason::NotConfirmedInGuild]]);
        $this->assertSame(0, Character::count());
        Saloon::mockClient()->assertSentCount(1, GetCharacterProfileRequest::class);

        $second = $this->commitSync(2);

        $this->runJob($second, $this->changes());

        $this->assertRows($second, [[self::MIRA, RowStatus::Processed, RowReason::Created]]);
        $this->assertSame(self::MIRA, Character::findOrFail(4242)->grm_guid);
        Saloon::mockClient()->assertSentCount(2, GetCharacterProfileRequest::class);
    }

    #[Test]
    public function it_stops_retrying_a_guid_once_a_later_job_processes_it(): void
    {
        $this->fakeGuildProfiles(['Other Guild', 'Regrowth']);
        $this->memberEntry(self::MIRA);
        $first = $this->commitSync(1);
        $this->runJob($first, $this->changes(members: [self::MIRA]));
        $second = $this->commitSync(2);
        $this->runJob($second, $this->changes());
        $third = $this->commitSync(3);

        $this->runJob($third, $this->changes());

        $this->assertRows($second, [[self::MIRA, RowStatus::Processed, RowReason::Created]]);
        $this->assertSame(UploadOutcome::NoChanges, $third->refresh()->outcome);
    }

    // ==================== name clashes ====================

    #[Test]
    public function it_resolves_a_rename_chain_in_the_same_job(): void
    {
        $brin = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-AAAA')->create(['name' => 'Brin']);
        $brinn = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-BBBB')->create(['name' => 'Brinn']);
        $sync = $this->commitSync(1);
        $this->memberEntry('Player-1-BBBB', ['name' => 'Brin']);
        $this->memberEntry('Player-1-AAAA', ['name' => 'Brinora']);

        $this->runJob($sync, $this->changes(members: ['Player-1-BBBB', 'Player-1-AAAA']));

        $this->assertSame('Brinora', $brin->refresh()->name);
        $this->assertSame('Brin', $brinn->refresh()->name);
        $this->assertRows($sync, [
            ['Player-1-AAAA', RowStatus::Processed, RowReason::Renamed],
            ['Player-1-BBBB', RowStatus::Processed, RowReason::Renamed],
        ]);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_leaves_a_true_swap_as_a_name_clash(): void
    {
        $brin = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-AAAA')->create(['name' => 'Brin']);
        $brinn = Character::factory()->forGameVersion($this->version)->withGrmGuid('Player-1-BBBB')->create(['name' => 'Brinn']);
        $sync = $this->commitSync(1);
        $this->memberEntry('Player-1-AAAA', ['name' => 'Brinn']);
        $this->memberEntry('Player-1-BBBB', ['name' => 'Brin']);

        $this->runJob($sync, $this->changes(members: ['Player-1-AAAA', 'Player-1-BBBB']));

        $this->assertSame('Brin', $brin->refresh()->name);
        $this->assertSame('Brinn', $brinn->refresh()->name);
        $this->assertRows($sync, [
            ['Player-1-AAAA', RowStatus::Failed, RowReason::NameClash],
            ['Player-1-BBBB', RowStatus::Failed, RowReason::NameClash],
        ]);
        $this->assertSame(2, $sync->refresh()->error_count);
    }

    // ==================== failure and side effects ====================

    #[Test]
    #[Group('error-handling')]
    public function it_records_failed_rows_for_every_changed_guid_when_the_job_fails(): void
    {
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::LIANE, ['name' => 'Liané Moonfall']);
        $this->leaverEntry(self::PAN, ['name' => 'Pan Bakewell']);
        $this->leaverEntry(self::ASHDOWN, ['name' => 'Pan Ashdown']);

        (new SyncRoster($sync->id, $sync->game_version_id, $this->changes(members: [self::MIRA, self::LIANE], leavers: [self::PAN], departed: [self::ASHDOWN])))
            ->failed(new RuntimeException('Database went away'));

        $sync->refresh();
        $this->assertSame(UploadOutcome::Failed, $sync->outcome);
        $this->assertRows($sync, [
            ['', RowStatus::Failed, RowReason::Error],
            [self::MIRA, RowStatus::Failed, RowReason::Error],
            [self::PAN, RowStatus::Failed, RowReason::Error],
            [self::LIANE, RowStatus::Failed, RowReason::Error],
            [self::ASHDOWN, RowStatus::Failed, RowReason::Error],
        ]);
        $this->assertSame('Database went away', $sync->rows()->where('guid', '')->value('message'));
        $this->assertSame('Liané Moonfall', $sync->rows()->where('guid', self::LIANE)->value('name'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_a_failed_row_for_a_member_that_throws_and_processes_the_rest(): void
    {
        Exceptions::fake();
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::PAN, ['name' => 'Pan Bakewell']);
        SyncMember::mock()
            ->shouldReceive('handle')
            ->andReturnUsing(function (GameVersion $gameVersion, MemberData $member): array {
                if ($member->guid === self::MIRA) {
                    throw new RuntimeException('Deadlock found');
                }

                return [new RowResultData(null, $member->guid, $member->name, RowStatus::Processed, RowReason::Updated)];
            });

        $this->runJob($sync, $this->changes(members: [self::MIRA, self::PAN]));

        $sync->refresh();
        $this->assertSame(UploadOutcome::Processed, $sync->outcome);
        $this->assertRows($sync, [
            [self::MIRA, RowStatus::Failed, RowReason::Error],
            [self::PAN, RowStatus::Processed, RowReason::Updated],
        ]);
        $this->assertSame('Deadlock found', $sync->rows()->where('guid', self::MIRA)->value('message'));
        $this->assertSame('Mira Ashvale', $sync->rows()->where('guid', self::MIRA)->value('name'));
        Exceptions::assertReported(RuntimeException::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_failed_rows_for_changed_alt_groups_when_the_job_fails(): void
    {
        $sync = $this->commitSync(1);

        (new SyncRoster($sync->id, $sync->game_version_id, $this->changes(altGroups: ['12', '15'])))
            ->failed(new RuntimeException('Database went away'));

        $sync->refresh();
        $rows = $sync->rows()->where('guid', '')->orderBy('id')->get();
        $this->assertSame(3, $rows->count());
        $this->assertSame(3, $sync->error_count);
        $this->assertSame('', $rows[1]->name);
        $this->assertSame(RowStatus::Failed, $rows[1]->status);
        $this->assertSame(RowReason::Error, $rows[1]->reason);
        $this->assertSame('alt group 12 was not linked: Database went away', $rows[1]->message);
        $this->assertSame('alt group 15 was not linked: Database went away', $rows[2]->message);

        $next = $this->commitSync(2);
        $this->runJob($next, $this->changes());
        $this->assertSame(UploadOutcome::NoChanges, $next->refresh()->outcome);
    }

    #[Test]
    public function it_fires_sync_committed_when_it_finishes(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $processed = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $empty = $this->commitSync(2);

        $this->runJob($processed, $this->changes(members: [self::MIRA]));
        $this->runJob($empty, $this->changes());

        Event::assertDispatched(SyncCommitted::class, fn (SyncCommitted $event): bool => $event->sync->is($processed)
            && $event->sync->outcome === UploadOutcome::Processed);
        Event::assertDispatched(SyncCommitted::class, fn (SyncCommitted $event): bool => $event->sync->is($empty)
            && $event->sync->outcome === UploadOutcome::NoChanges);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_fires_sync_committed_after_a_failure(): void
    {
        $sync = $this->commitSync(1);

        (new SyncRoster($sync->id, $sync->game_version_id, $this->changes()))->failed(new RuntimeException('Timed out'));

        Event::assertDispatched(SyncCommitted::class, fn (SyncCommitted $event): bool => $event->sync->is($sync)
            && $event->sync->outcome === UploadOutcome::Failed);
    }

    #[Test]
    public function it_never_dispatches_fetch_guild_roster_or_notifies(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create(['is_main' => false]);
        $this->panBakewell()->withGrmGuid(self::PAN)->create(['is_main' => false]);
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        $this->memberEntry(self::LIANE, ['name' => 'Liané Moonfall']);
        $this->altGroupEntry('12', 'Mira Ashvale', [self::MIRA, self::PAN]);

        $this->runJob($sync, $this->changes(members: [self::MIRA, self::LIANE], altGroups: ['12'], previousAltGroups: ['12' => null]));
        (new SyncRoster($sync->id, $sync->game_version_id, $this->changes(members: [self::MIRA])))->failed(new RuntimeException('Timed out'));

        Bus::assertNotDispatched(FetchGuildRoster::class);
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_flushes_the_attendance_and_characters_caches_when_a_row_was_processed(): void
    {
        $this->mira()->withGrmGuid(self::MIRA)->create();
        $sync = $this->commitSync(1);
        $this->memberEntry(self::MIRA);
        Cache::tags(['attendance'])->put('probe', true);
        Cache::tags(['characters'])->put('probe', true);

        $this->runJob($sync, $this->changes(members: [self::MIRA]));

        $this->assertFalse(Cache::tags(['attendance'])->has('probe'));
        $this->assertFalse(Cache::tags(['characters'])->has('probe'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_flushes_the_attendance_and_characters_caches_when_the_job_fails(): void
    {
        $sync = $this->commitSync(1);
        Cache::tags(['attendance'])->put('probe', true);
        Cache::tags(['characters'])->put('probe', true);

        (new SyncRoster($sync->id, $sync->game_version_id, $this->changes()))->failed(new RuntimeException('Timed out'));

        $this->assertFalse(Cache::tags(['attendance'])->has('probe'));
        $this->assertFalse(Cache::tags(['characters'])->has('probe'));
    }

    #[Test]
    public function it_keeps_the_caches_when_no_row_was_processed(): void
    {
        $sync = $this->commitSync(1);
        $this->leaverEntry(self::ASHDOWN, ['name' => 'Pan Ashdown']);
        Cache::tags(['attendance'])->put('probe', true);
        Cache::tags(['characters'])->put('probe', true);

        $this->runJob($sync, $this->changes(leavers: [self::ASHDOWN]));

        $this->assertRows($sync, [[self::ASHDOWN, RowStatus::Skipped, RowReason::NotTracked]]);
        $this->assertTrue(Cache::tags(['attendance'])->has('probe'));
        $this->assertTrue(Cache::tags(['characters'])->has('probe'));
    }

    // ==================== job contract ====================

    #[Test]
    #[Group('contract')]
    public function it_declares_its_queue_contract(): void
    {
        $sync = $this->commitSync(1);
        $job = new SyncRoster($sync->id, $sync->game_version_id, $this->changes());
        $class = new ReflectionClass(SyncRoster::class);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(0, $class->getAttributes(Tries::class)[0]->newInstance()->tries);
        $this->assertSame(3, $class->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        $this->assertSame(60, $class->getAttributes(Backoff::class)[0]->newInstance()->backoff);
        $this->assertSame(900, $class->getAttributes(Timeout::class)[0]->newInstance()->timeout);
        $this->assertTrue(now()->addHours(6)->equalTo($job->retryUntil()));
        $this->assertSame(['grm-sync', "game-version:{$this->version->id}"], $job->tags());

        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame("grm-sync:{$this->version->id}", $middleware[0]->key);
        $this->assertSame(60, $middleware[0]->releaseAfter);
        $this->assertSame(960, $middleware[0]->expiresAfter);
    }

    // ==================== helpers ====================

    /**
     * Run the job's handler for the sync with faked queue interactions.
     */
    private function runJob(Upload $sync, SyncChangesData $changes): SyncRoster
    {
        $job = (new SyncRoster($sync->id, $sync->game_version_id, $changes))->withFakeQueueInteractions();

        $job->handle();

        return $job;
    }

    /**
     * Create a desktop sync of the test version committed as the given revision, with its roster revision.
     */
    private function commitSync(int $revision, UploadOutcome $outcome = UploadOutcome::Received, ?CarbonInterface $committedAt = null): Upload
    {
        $sync = Upload::factory()->for($this->version)->committed($revision)->withOutcome($outcome)->create();

        if (! RosterRevision::where('game_version_id', $this->version->id)->where('revision', $revision)->exists()) {
            RosterRevision::factory()->create([
                'game_version_id' => $this->version->id,
                'revision' => $revision,
                'upload_id' => $sync->id,
                'committed_at' => $committedAt ?? now(),
            ]);
        }

        return $sync;
    }

    /**
     * Build the changes a commit applied.
     *
     * @param  list<string>  $members
     * @param  list<string>  $leavers
     * @param  list<string>  $altGroups
     * @param  list<string>  $departed
     * @param  array<string, array<string, mixed>|null>  $previousAltGroups
     */
    private function changes(
        array $members = [],
        array $leavers = [],
        array $altGroups = [],
        array $departed = [],
        array $previousAltGroups = [],
    ): SyncChangesData {
        return new SyncChangesData(
            members: $members,
            leavers: $leavers,
            alt_groups: $altGroups,
            departed_members: $departed,
            previous_alt_groups: $previousAltGroups,
            ban_ids: [],
        );
    }

    /**
     * Store a member entry for the GUID, a level 60 Mira Ashvale at rank index 3 unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function memberEntry(string $guid, array $overrides = [], int $epoch = self::EPOCH, int $revision = 1): RosterEntry
    {
        return $this->rosterEntry(RosterEntryKind::Member, $guid, MemberData::from([
            'guid' => $guid,
            'name' => 'Mira Ashvale',
            'rankIndex' => 3,
            'level' => 60,
            'class' => 'WARLOCK',
            'race' => 'Scourge',
            'altGroup' => null,
            'history' => [['type' => 'joined', 'date' => '2026-08-02']],
            'epoch' => $epoch,
            ...$overrides,
        ]), $epoch, $revision);
    }

    /**
     * Store a leaver entry for the GUID, left on 2026-10-01 unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function leaverEntry(string $guid, array $overrides = [], int $epoch = self::EPOCH, int $revision = 1): RosterEntry
    {
        return $this->rosterEntry(RosterEntryKind::Leaver, $guid, LeaverData::from([
            'guid' => $guid,
            'name' => 'Mira Ashvale',
            'rankIndex' => 4,
            'level' => 60,
            'class' => 'WARLOCK',
            'race' => 'Scourge',
            'history' => [['type' => 'joined', 'date' => '2026-08-02'], ['type' => 'left', 'date' => '2026-10-01']],
            'epoch' => $epoch,
            ...$overrides,
        ]), $epoch, $revision);
    }

    /**
     * Store an alt group entry.
     *
     * @param  list<string>  $members
     */
    private function altGroupEntry(string $id, ?string $main, array $members): RosterEntry
    {
        return $this->rosterEntry(RosterEntryKind::AltGroup, $id, new AltGroupData($id, $main, $members, self::EPOCH), self::EPOCH, 1);
    }

    private function rosterEntry(RosterEntryKind $kind, string $key, MemberData|LeaverData|AltGroupData $payload, int $epoch, int $revision): RosterEntry
    {
        return RosterEntry::factory()->create([
            'game_version_id' => $this->version->id,
            'kind' => $kind,
            'key' => $key,
            'payload' => $payload,
            'epoch' => $epoch,
            'revision' => $revision,
        ]);
    }

    /**
     * Record an outcome for the GUID against an earlier sync, as the GUID's latest outcome.
     */
    private function uploadRow(Upload $sync, string $guid, RowStatus $status, RowReason $reason): UploadRow
    {
        UploadRow::where('guid', $guid)->where('upload_id', '!=', $sync->id)->update(['is_latest' => false]);

        return UploadRow::factory()->for($sync)->create(['guid' => $guid, 'status' => $status, 'reason' => $reason]);
    }

    /**
     * Fake Blizzard character profiles for character 4242, each request listing the next guild in turn.
     *
     * @param  list<string>  $guilds
     */
    private function fakeGuildProfiles(array $guilds): void
    {
        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE),
            GetCharacterProfileRequest::class => function () use (&$guilds): MockResponse {
                return MockResponse::make(body: [
                    ...$this->makeCharacterProfileResponse(),
                    'id' => 4242,
                    'guild' => ['key' => ['href' => 'https://example.test/guild/1'], 'name' => array_shift($guilds), 'id' => 1],
                ]);
            },
        ]);
    }

    /**
     * Link the characters to each other in both directions.
     */
    private function link(Character ...$characters): void
    {
        foreach ($characters as $character) {
            $character->linkedCharacters()->syncWithoutDetaching(
                collect($characters)->reject(fn (Character $other): bool => $other->is($character))->pluck('id')->all(),
            );
        }
    }

    /**
     * Assert the sync's stored rows by GUID, status and reason, sorted by GUID then reason.
     *
     * @param  list<array{0: string, 1: RowStatus, 2: RowReason}>  $expected
     */
    private function assertRows(Upload $sync, array $expected): void
    {
        $actual = $sync->rows()->orderBy('guid')->orderBy('reason')->get()
            ->map(fn (UploadRow $row): array => [$row->guid, $row->status, $row->reason])
            ->all();

        $this->assertSame($expected, $actual);
    }

    private function mira(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withSurname('Ashvale')->state(['name' => 'Mira']);
    }

    private function panBakewell(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withSurname('Bakewell')->state(['name' => 'Pan']);
    }

    private function liane(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withSurname('Moonfall')->state(['name' => 'Liané']);
    }
}
