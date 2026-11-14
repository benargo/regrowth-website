<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\SyncLeaver;
use App\Data\GuildRosterManager\LeaverData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use Carbon\CarbonInterface;
use Database\Factories\CharacterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class SyncLeaverTest extends TestCase
{
    use RefreshDatabase;

    private const string GUID = 'Player-5826-0A1B2C40';

    private GameVersion $version;

    private CarbonInterface $syncStartedAt;

    protected function setUp(): void
    {
        parent::setUp();

        Saloon::fake([]);
        $this->version = GameVersion::factory()->withSurnames()->fetchableRoster()->create(['guild_name' => 'Regrowth']);
        $this->syncStartedAt = Carbon::parse('2026-11-14 12:00:00', 'UTC');
    }

    #[Test]
    public function it_marks_a_matched_leaver_as_left_on_their_last_leave_date(): void
    {
        $character = $this->gone()->withGrmGuid(self::GUID)->create();

        $result = SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, null);

        $this->assertSame(RowStatus::Processed, $result->status);
        $this->assertSame(RowReason::Left, $result->reason);
        $this->assertSame($character->id, $result->characterId);
        $this->assertTrue(Carbon::parse('2026-10-01 00:00:00', config('app.timezone'))->equalTo($character->refresh()->left_guild_at));
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_marks_a_matched_leaver_as_left_at_the_raw_epoch(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1790865600, 'UTC');
        $character = $this->gone()->withGrmGuid(self::GUID)->create();

        SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, $evidenceAt);

        $this->assertTrue($evidenceAt->equalTo($character->refresh()->left_guild_at));
        $this->assertSame(config('app.timezone'), $character->left_guild_at->getTimezone()->getName());
    }

    #[Test]
    public function it_records_an_unmatched_leaver_as_not_tracked_without_creating_one(): void
    {
        $result = SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, null);

        $this->assertSame(RowStatus::Skipped, $result->status);
        $this->assertSame(RowReason::NotTracked, $result->reason);
        $this->assertNull($result->characterId);
        $this->assertSame(0, Character::count());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_uses_the_sync_start_time_when_there_is_no_leave_event(): void
    {
        $character = $this->gone()->withGrmGuid(self::GUID)->create();

        SyncLeaver::run($this->version, $this->leaver(['history' => []]), $this->syncStartedAt, null);

        $this->assertTrue($this->syncStartedAt->equalTo($character->refresh()->left_guild_at));
    }

    #[Test]
    public function it_marks_a_leaver_left_even_with_newer_blizzard_rank_evidence(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1790865600, 'UTC');
        $character = $this->gone()->withGrmGuid(self::GUID)
            ->rankObserved($evidenceAt->copy()->addDay()->setTimezone(config('app.timezone')))->create();

        $result = SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, $evidenceAt);

        $this->assertSame(RowReason::Left, $result->reason);
        $this->assertSame(RowStatus::Processed, $result->status);
        $this->assertTrue($evidenceAt->equalTo($character->refresh()->left_guild_at));
    }

    #[Test]
    public function it_keeps_a_member_with_newer_evidence_for_a_retry_leaver_row(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1790865600, 'UTC');
        $character = $this->gone()->withGrmGuid(self::GUID)
            ->rankObserved($evidenceAt->copy()->addDay()->setTimezone(config('app.timezone')))->create();

        $result = SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, $evidenceAt, true);

        $this->assertSame(RowStatus::Processed, $result->status);
        $this->assertSame(RowReason::Updated, $result->reason);
        $this->assertNull($character->refresh()->left_guild_at);
    }

    #[Test]
    public function it_marks_a_retry_leaver_left_when_the_departure_is_newer_than_the_rank_evidence(): void
    {
        $evidenceAt = Carbon::createFromTimestamp(1790865600, 'UTC');
        $character = $this->gone()->withGrmGuid(self::GUID)
            ->rankObserved($evidenceAt->copy()->subDay()->setTimezone(config('app.timezone')))->create();

        $result = SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, $evidenceAt, true);

        $this->assertSame(RowReason::Left, $result->reason);
        $this->assertNotNull($character->refresh()->left_guild_at);
    }

    #[Test]
    public function it_matches_by_name_and_records_the_guid_and_membership_periods(): void
    {
        $character = $this->gone()->create();

        SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, null);

        $this->assertSame(self::GUID, $character->refresh()->grm_guid);
        $this->assertDatabaseHas('character_guild_memberships', ['character_id' => $character->id, 'left_on' => '2026-10-01']);
    }

    #[Test]
    public function it_links_the_leavers_ban(): void
    {
        $character = $this->gone()->withGrmGuid(self::GUID)->forUser()->create();
        $ban = Ban::factory()->create(['game_version_id' => $this->version->id, 'grm_guid' => self::GUID]);

        SyncLeaver::run($this->version, $this->leaver(), $this->syncStartedAt, null);

        $ban->refresh();
        $this->assertSame($character->id, $ban->character_id);
        $this->assertSame($character->user_id, $ban->user_id);
    }

    // ==================== helpers ====================

    /**
     * A character factory for Gone Fishing in the test version.
     */
    private function gone(): CharacterFactory
    {
        return Character::factory()->forGameVersion($this->version)->withSurname('Fishing')->state(['name' => 'Gone']);
    }

    /**
     * Build the fixture's first leaver (Gone Fishing) with the given overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function leaver(array $overrides = []): LeaverData
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/GuildRosterManager/forever-roster.json')), true);

        return LeaverData::from([...$fixture['leavers'][0], ...$overrides]);
    }
}
