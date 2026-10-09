<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Models\Character;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
    }

    // ==================== happy path ====================

    #[Test]
    public function it_creates_pivot_entries_for_characters_with_count_attendance_ranks(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'abc123']);

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
        $character = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'bench001']);

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
        $character = Character::factory()->create(['name' => 'Anduin', 'rank_id' => $rank->id]);
        $firstReport = Report::factory()->forGuild($this->guild)->create(['code' => 'page1']);
        $secondReport = Report::factory()->forGuild($this->guild)->create(['code' => 'page2']);

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
        $character = Character::factory()->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'skp001']);

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
        $character = Character::factory()->create(['name' => 'Illidan', 'rank_id' => null]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'norank1']);

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
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'unk001']);

        $guildAttendance = $this->attendanceRecord('unk001', [['name' => 'UnknownPlayer', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    #[Test]
    public function it_skips_attendance_records_for_reports_not_in_the_database(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);

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
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $originalTime = now()->subHour();
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'touch01', 'updated_at' => $originalTime]);

        $guildAttendance = $this->attendanceRecord('touch01', [['name' => 'Thrall', 'presence' => 1]], '2025-06-01');

        $this->fakeAttendancePages([[$guildAttendance]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertGreaterThan($originalTime, $report->fresh()->updated_at);
    }

    #[Test]
    public function it_does_not_touch_the_report_when_no_attendance_data_is_synced(): void
    {
        $originalTime = now()->subHour();
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'notouch1', 'updated_at' => $originalTime]);

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
        $character = Character::factory()->create(['name' => 'Rexxar', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'dup001']);

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
        $character = Character::factory()->create(['name' => 'Varian', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'exists1']);

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
        Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $otherGuildsReport = Report::factory()->forGuild()->create(['code' => 'other1']);
        $this->fakeAttendancePages([[$this->attendanceRecord('other1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', ['raid_report_id' => $otherGuildsReport->id]);
    }

    #[Test]
    public function it_ignores_reports_without_a_guild(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $guildlessReport = Report::factory()->create(['code' => 'orphan1']);
        $this->fakeAttendancePages([[$this->attendanceRecord('orphan1', [['name' => 'Thrall', 'presence' => 1]])]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseMissing('pivot_characters_raid_reports', ['raid_report_id' => $guildlessReport->id]);
    }

    #[Test]
    public function it_keeps_existing_attendance_rows_it_does_not_resync(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->forGuild($this->guild)->create(['code' => 'kept01']);
        $report->characters()->attach($character->id, ['presence' => 2]);
        $this->fakeAttendancePages([[]]);

        $this->runJob(new FetchAttendanceData($this->guild));

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 2,
        ]);
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
