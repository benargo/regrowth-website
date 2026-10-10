<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Concerns\FakesWarcraftLogs;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class FetchAttendanceDataTest extends TestCase
{
    use FakesWarcraftLogs;
    use RefreshDatabase;

    private Guild $guild;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
        $this->gameVersion = GameVersion::factory()->forGuild($this->guild)->create(['release_date' => '2025-01-01']);
    }

    // ==================== happy path ====================

    #[Test]
    public function it_creates_pivot_entries_for_characters_with_count_attendance_ranks(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'abc123']);

        $guildAttendance = $this->attendanceRecord('abc123', [['name' => 'Thrall', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 1,
        ]);
    }

    #[Test]
    public function it_stores_the_correct_presence_value(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'bench001']);

        $guildAttendance = $this->attendanceRecord('bench001', [['name' => 'Jaina', 'presence' => 2]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 2,
        ]);
    }

    #[Test]
    public function it_queries_the_guild_on_its_namespace_host(): void
    {
        $this->fakeAttendancePages([[]]);

        $this->runJob(new FetchAttendanceData(Guild::factory()->create(['id' => 555, 'namespace' => WarcraftLogsNamespace::Classic])));

        Saloon::assertSent(function (Request $request, Response $response): bool {
            return $request instanceof GetGuildAttendanceRequest
                && str_starts_with($response->getPendingRequest()->getUrl(), 'https://classic.warcraftlogs.com/')
                && data_get($request->body()->all(), 'variables.id') === 555;
        });
    }

    #[Test]
    public function it_syncs_attendance_from_every_page(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Anduin', 'rank_id' => $rank->id]);
        $firstReport = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'page1']);
        $secondReport = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'page2']);

        $this->fakeAttendancePages([
            [$this->attendanceRecord('page1', [['name' => 'Anduin', 'presence' => 1]])],
            [$this->attendanceRecord('page2', [['name' => 'Anduin', 'presence' => 2]], '2025-06-08')],
        ]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $character->id, 'raid_report_id' => $firstReport->id, 'presence' => 1]);
        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $character->id, 'raid_report_id' => $secondReport->id, 'presence' => 2]);
    }

    // ==================== rank filtering ====================

    #[Test]
    public function it_skips_characters_whose_ranks_do_not_count_attendance(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'skp001']);

        $guildAttendance = $this->attendanceRecord('skp001', [['name' => 'Sylvanas', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_skips_characters_with_no_rank(): void
    {
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Illidan', 'rank_id' => null]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'norank1']);

        $guildAttendance = $this->attendanceRecord('norank1', [['name' => 'Illidan', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    // ==================== missing records ====================

    #[Test]
    public function it_skips_players_not_found_in_the_database(): void
    {
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'unk001']);

        $guildAttendance = $this->attendanceRecord('unk001', [['name' => 'UnknownPlayer', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    #[Test]
    public function it_skips_attendance_records_for_reports_not_in_the_database(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas', 'rank_id' => $rank->id]);

        // No report created — simulates attendance for a report not in the DB
        $guildAttendance = $this->attendanceRecord('missing1', [['name' => 'Arthas', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    // ==================== touching ====================

    #[Test]
    public function it_touches_the_report_updated_at_when_attendance_is_synced(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $originalTime = now()->subHour();
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'touch01', 'updated_at' => $originalTime]);

        $guildAttendance = $this->attendanceRecord('touch01', [['name' => 'Thrall', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertGreaterThan($originalTime, $report->fresh()->updated_at);
    }

    #[Test]
    public function it_does_not_touch_the_report_when_no_attendance_data_is_synced(): void
    {
        $originalTime = now()->subHour();
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'notouch1', 'updated_at' => $originalTime]);

        $guildAttendance = $this->attendanceRecord('notouch1', [], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertEquals($originalTime->toDateTimeString(), $report->fresh()->updated_at->toDateTimeString());
    }

    // ==================== edge cases ====================

    #[Test]
    public function it_handles_duplicate_character_entries_without_throwing(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Rexxar', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'dup001']);

        $guildAttendance = $this->attendanceRecord('dup001', [['name' => 'Rexxar', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        // Run the job twice to simulate concurrent execution or a re-run
        $job = new FetchAttendanceData($this->guild);
        $this->runJob($job);
        $this->runJob($job);

        $this->assertDatabaseCount('pivot_characters_raid_reports', 1);
        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 1,
        ]);
    }

    #[Test]
    public function it_only_processes_attendance_for_reports_in_the_database(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Varian', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'exists1']);

        $existsRecord = $this->attendanceRecord('exists1', [['name' => 'Varian', 'presence' => 1]], '2025-06-01');

        $missingRecord = $this->attendanceRecord('notindb1', [['name' => 'Varian', 'presence' => 1]], '2025-06-02');

        $this->fakeAttendancePages([[$existsRecord, $missingRecord]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['raid_report_id' => $report->id]);
        $this->assertDatabaseCount('pivot_characters_raid_reports', 1);
    }

    // ==================== guild scope ====================

    #[Test]
    public function it_ignores_reports_of_another_guild(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $otherGuildsReport = Report::factory()->forGuild()->create(['code' => 'other1']);
        $this->fakeAttendancePages([[$this->attendanceRecord('other1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', ['raid_report_id' => $otherGuildsReport->id]);
    }

    #[Test]
    public function it_ignores_reports_without_a_guild(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $guildlessReport = Report::factory()->create(['code' => 'orphan1']);
        $this->fakeAttendancePages([[$this->attendanceRecord('orphan1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', ['raid_report_id' => $guildlessReport->id]);
    }

    #[Test]
    public function it_keeps_existing_attendance_rows_it_does_not_resync(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'kept01']);
        $report->characters()->attach($character->id, ['presence' => 2]);
        $this->fakeAttendancePages([[]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 2,
        ]);
    }

    // ==================== game version scope ====================

    #[Test]
    public function it_matches_players_only_among_the_reports_game_version(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $otherThrall = Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'scope1']);
        $this->fakeAttendancePages([[$this->attendanceRecord('scope1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $thrall->id, 'raid_report_id' => $report->id]);
        $this->assertDatabaseMissing('pivot_characters_raid_reports', ['character_id' => $otherThrall->id]);
    }

    #[Test]
    public function it_resolves_each_report_in_its_own_game_version(): void
    {
        $rank = GuildRank::factory()->create();
        $laterGameVersion = GameVersion::factory()->forGuild($this->guild)->create(['release_date' => '2026-01-01']);
        $thrall = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $laterThrall = Character::factory()->forGameVersion($laterGameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'early1']);
        $laterReport = Report::factory()->forGameVersion($laterGameVersion)->create(['code' => 'later1']);
        $this->fakeAttendancePages([[
            $this->attendanceRecord('early1', [['name' => 'Thrall', 'presence' => 1]]),
            $this->attendanceRecord('later1', [['name' => 'Thrall', 'presence' => 2]]),
        ]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $thrall->id, 'raid_report_id' => $report->id, 'presence' => 1]);
        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $laterThrall->id, 'raid_report_id' => $laterReport->id, 'presence' => 2]);
        $this->assertDatabaseCount('pivot_characters_raid_reports', 2);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_and_logs_an_ambiguous_player_name(): void
    {
        Log::spy();
        $rank = GuildRank::factory()->create();
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Tears', 'rank_id' => $rank->id]);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Teärs', 'rank_id' => $rank->id]);
        Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'amb001']);
        $this->fakeAttendancePages([[$this->attendanceRecord('amb001', [['name' => 'TEARS', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Skipped a character name that matches more than one character.'
            && $context['source'] === 'warcraftlogs.attendance'
            && $context['game_version_id'] === $this->gameVersion->id
            && $context['name'] === 'TEARS');
    }

    #[Test]
    public function it_does_not_let_a_rank_that_does_not_count_attendance_make_a_name_ambiguous(): void
    {
        Log::spy();
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Tears', 'rank_id' => GuildRank::factory()->doesNotCountAttendance()->create()->id]);
        $accentedTears = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Teärs', 'rank_id' => GuildRank::factory()->create()->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'rank01']);
        $this->fakeAttendancePages([[$this->attendanceRecord('rank01', [['name' => 'TEARS', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $accentedTears->id, 'raid_report_id' => $report->id]);
        Log::shouldNotHaveReceived('error');
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_and_logs_a_report_with_no_game_version_and_keeps_its_rows(): void
    {
        Log::spy();
        $thrall = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => GuildRank::factory()->create()->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'nover1', 'start_time' => '2024-06-01 20:00:00']);
        $report->characters()->attach($thrall->id, ['presence' => 2]);
        $this->fakeAttendancePages([[$this->attendanceRecord('nover1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertNull($report->fresh()->game_version_id);
        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $thrall->id, 'raid_report_id' => $report->id, 'presence' => 2]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'nover1'));
    }

    #[Test]
    public function it_keeps_rows_for_characters_outside_the_version_or_without_one(): void
    {
        $rank = GuildRank::factory()->create();
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $otherVersions = Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $versionless = Character::factory()->create(['name' => 'Anduin', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'keep01']);
        $report->characters()->attach([$otherVersions->id => ['presence' => 1], $versionless->id => ['presence' => 2]]);
        $this->fakeAttendancePages([[$this->attendanceRecord('keep01', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $otherVersions->id, 'raid_report_id' => $report->id, 'presence' => 1]);
        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $versionless->id, 'raid_report_id' => $report->id, 'presence' => 2]);
    }

    // ==================== rate limiting ====================

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_until_the_points_reset_when_rate_limited(): void
    {
        $this->freezeTime();
        $this->app->make(RateLimitResetCache::class)->put(
            new RateLimitData(limitPerHour: 3600, pointsSpentThisHour: 3600.0, pointsResetIn: 900),
        );

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => MockResponse::make(['error' => 'Too Many Requests'], 429),
        ]);

        $job = (new FetchAttendanceData($this->guild))->withFakeQueueInteractions();
        $job->handle($this->makeConnector());

        $job->assertReleased(delay: 900);
        $job->assertNotFailed();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_and_writes_nothing_when_rate_limited_on_a_later_page(): void
    {
        $this->freezeTime();
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Thrall', 'rank_id' => GuildRank::factory()->create()->id]);
        Report::factory()->forGameVersion($this->gameVersion)->create(['code' => 'first1']);

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                if ((int) data_get($pendingRequest->body()->all(), 'variables.page', 1) === 2) {
                    return MockResponse::make(['error' => 'Too Many Requests'], 429);
                }

                return MockResponse::make(['data' => ['guildData' => ['guild' => ['attendance' => [
                    'data' => [$this->attendanceRecord('first1', [['name' => 'Thrall', 'presence' => 1]])],
                    'current_page' => 1,
                    'has_more_pages' => true,
                ]]]]]);
            },
        ]);

        $job = (new FetchAttendanceData($this->guild))->withFakeQueueInteractions();
        $job->handle($this->makeConnector());

        $job->assertReleased(delay: 3600);
        $job->assertNotFailed();
        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    // ==================== concurrency & resilience ====================

    #[Test]
    #[Group('contract')]
    public function it_locks_overlapping_runs_per_guild(): void
    {
        $first = new FetchAttendanceData($this->guild);
        $second = new FetchAttendanceData(Guild::factory()->create());

        $this->assertNotSame($this->overlapLock($first)->getLockKey($first), $this->overlapLock($second)->getLockKey($second));

        $again = new FetchAttendanceData($this->guild);
        $this->assertSame($this->overlapLock($first)->getLockKey($first), $this->overlapLock($again)->getLockKey($again));
        $this->assertSame($first->timeout, $this->overlapLock($first)->expiresAfter);
    }

    #[Test]
    #[Group('contract')]
    public function it_retries_for_two_hours_but_fails_on_its_third_exception_or_first_timeout(): void
    {
        $this->freezeTime();

        $class = new ReflectionClass(FetchAttendanceData::class);
        $maxExceptions = $class->getAttributes(MaxExceptions::class);

        $this->assertEquals(now()->addHours(2), (new FetchAttendanceData($this->guild))->retryUntil());
        $this->assertCount(1, $maxExceptions);
        $this->assertSame(3, $maxExceptions[0]->newInstance()->maxExceptions);
        $this->assertCount(1, $class->getAttributes(FailOnTimeout::class));
    }

    #[Test]
    #[Group('contract')]
    public function it_tags_the_job_with_its_guild(): void
    {
        $this->assertSame(
            ['warcraftlogs', 'attendance', 'warcraft-logs-guild:774848'],
            (new FetchAttendanceData($this->guild))->tags(),
        );
    }

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        Saloon::fake([]);

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new FetchAttendanceData($this->guild);
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    // ==================== helpers ====================

    #[Test]
    #[Group('contract')]
    public function it_is_deleted_when_its_guild_no_longer_exists(): void
    {
        $class = new ReflectionClass(FetchAttendanceData::class);

        $this->assertCount(1, $class->getAttributes(DeleteWhenMissingModels::class));
    }

    private function runJob(FetchAttendanceData $job): void
    {
        $job->handle($this->makeConnector());
    }

    private function overlapLock(FetchAttendanceData $job): WithoutOverlapping
    {
        return collect($job->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     */
    private function fakeAttendancePages(array $pages): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => function (PendingRequest $pendingRequest) use ($pages): MockResponse {
                $page = (int) data_get($pendingRequest->body()->all(), 'variables.page', 1);

                return MockResponse::make(['data' => ['guildData' => ['guild' => ['attendance' => [
                    'data' => $pages[$page - 1] ?? [],
                    'current_page' => $page,
                    'has_more_pages' => $page < count($pages),
                ]]]]]);
            },
        ]);
    }

    /**
     * @param  array<int, array{name: string, presence: int}>  $players
     * @return array<string, mixed>
     */
    private function attendanceRecord(string $code, array $players, string $startTime = '2025-06-01'): array
    {
        return [
            'code' => $code,
            'startTime' => Carbon::parse($startTime)->getTimestampMs(),
            'players' => $players,
            'zone' => null,
        ];
    }
}
