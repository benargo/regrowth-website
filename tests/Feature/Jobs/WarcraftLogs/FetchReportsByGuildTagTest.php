<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\Requests\GetReportsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
class FetchReportsByGuildTagTest extends TestCase
{
    use FakesWarcraftLogs;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_persists_reports_for_the_given_guild_tag(): void
    {
        $guildTag = $this->fetchableGuildTag();

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('ABC123', 'Test Report', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id),
        ]);

        $this->assertDatabaseHas('reports', ['code' => 'ABC123', 'title' => 'Test Report', 'guild_tag_id' => $guildTag->id]);
    }

    #[Test]
    public function it_upserts_the_zone_when_persisting_a_report_with_a_zone(): void
    {
        $guildTag = $this->fetchableGuildTag();

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('ZONE01', 'Zone Test Report', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id, zone: [
                'id' => 2000,
                'name' => 'Karazhan',
                'difficulties' => [['id' => 3, 'name' => 'Normal', 'sizes' => [10]]],
                'expansion' => ['id' => 1001, 'name' => 'TBC'],
            ]),
        ]);

        $this->assertDatabaseHas('warcraft_logs_zones', ['id' => 2000, 'name' => 'Karazhan']);
        $this->assertDatabaseHas('reports', ['code' => 'ZONE01', 'zone_id' => 2000]);
    }

    #[Test]
    public function it_queries_the_guild_tag_on_its_game_versions_namespace_host(): void
    {
        $guildTag = $this->fetchableGuildTag(WarcraftLogsNamespace::Classic);

        $this->runJob(new FetchReportsByGuildTag($guildTag));

        Saloon::assertSent(function (Request $request, Response $response) use ($guildTag): bool {
            return $request instanceof GetReportsRequest
                && str_starts_with($response->getPendingRequest()->getUrl(), 'https://classic.warcraftlogs.com/')
                && data_get($request->body()->all(), 'variables.guildTagID') === $guildTag->id;
        });
    }

    #[Test]
    public function it_persists_reports_from_every_page(): void
    {
        $guildTag = $this->fetchableGuildTag();
        $this->fakeReportPages([
            [$this->reportPayload('PAGE01', 'First', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id)],
            [$this->reportPayload('PAGE02', 'Second', Carbon::parse('2025-01-08 19:00:00'), Carbon::parse('2025-01-08 22:00:00'), guildTagId: $guildTag->id)],
        ]);

        (new FetchReportsByGuildTag($guildTag))->handle($this->makeConnector());

        $this->assertDatabaseHas('reports', ['code' => 'PAGE01']);
        $this->assertDatabaseHas('reports', ['code' => 'PAGE02']);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_keeps_one_report_when_the_same_code_appears_on_two_pages(): void
    {
        $guildTag = $this->fetchableGuildTag();
        $this->fakeReportPages([
            [$this->reportPayload('DUP001', 'Original', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id)],
            [$this->reportPayload('DUP001', 'Renamed', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id)],
        ]);

        (new FetchReportsByGuildTag($guildTag))->handle($this->makeConnector());

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['code' => 'DUP001', 'title' => 'Renamed']);
    }

    // ==================== time filters ====================

    #[Test]
    public function it_sends_since_as_the_start_time(): void
    {
        $since = Carbon::parse('2025-01-01');

        $this->runJob(new FetchReportsByGuildTag($this->fetchableGuildTag(), since: $since));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetReportsRequest
            && (int) data_get($request->body()->all(), 'variables.startTime') === $since->getTimestampMs()
            && data_get($request->body()->all(), 'variables.endTime') === null);
    }

    #[Test]
    public function it_sends_before_as_the_end_time(): void
    {
        $before = Carbon::parse('2025-06-01');

        $this->runJob(new FetchReportsByGuildTag($this->fetchableGuildTag(), before: $before));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetReportsRequest
            && data_get($request->body()->all(), 'variables.startTime') === null
            && (int) data_get($request->body()->all(), 'variables.endTime') === $before->getTimestampMs());
    }

    #[Test]
    public function it_sends_both_time_filters(): void
    {
        $since = Carbon::parse('2025-01-01');
        $before = Carbon::parse('2025-06-01');

        $this->runJob(new FetchReportsByGuildTag($this->fetchableGuildTag(), since: $since, before: $before));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetReportsRequest
            && (int) data_get($request->body()->all(), 'variables.startTime') === $since->getTimestampMs()
            && (int) data_get($request->body()->all(), 'variables.endTime') === $before->getTimestampMs());
    }

    // ==================== guild tag association ====================

    #[Test]
    public function it_associates_a_report_naming_another_stored_guild_tag_with_that_tag(): void
    {
        $guildTag = $this->fetchableGuildTag();
        $otherTag = GuildTag::factory()->withoutPhase()->create();

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('OTHER1', 'Other', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $otherTag->id),
        ]);

        $this->assertDatabaseHas('reports', ['code' => 'OTHER1', 'guild_tag_id' => $otherTag->id]);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_dissociates_a_report_whose_guild_tag_is_not_stored(): void
    {
        $guildTag = $this->fetchableGuildTag();
        Report::factory()->withGuildTag($guildTag)->create(['code' => 'GONE01']);

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('GONE01', 'Unknown Tag', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: 999999),
        ]);

        $this->assertDatabaseHas('reports', ['code' => 'GONE01', 'guild_tag_id' => null]);
    }

    #[Test]
    public function it_dissociates_a_report_with_no_guild_tag(): void
    {
        $guildTag = $this->fetchableGuildTag();
        Report::factory()->withGuildTag($guildTag)->create(['code' => 'NOTAG1']);

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('NOTAG1', 'No Tag', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00')),
        ]);

        $this->assertDatabaseHas('reports', ['code' => 'NOTAG1', 'guild_tag_id' => null]);
    }

    #[Test]
    public function it_stores_the_game_version_of_the_reports_guild_tag(): void
    {
        $guildTag = $this->fetchableGuildTag();

        $this->runJob(new FetchReportsByGuildTag($guildTag), [
            $this->reportPayload('ABC123', 'Test Report', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id),
        ]);

        $this->assertDatabaseHas('reports', ['code' => 'ABC123', 'game_version_id' => $guildTag->phase->game_version_id]);
    }

    // ==================== incomplete game version chain ====================

    #[Test]
    public function it_skips_a_guild_tag_without_a_phase(): void
    {
        Saloon::fake([]);
        Log::spy();

        (new FetchReportsByGuildTag(GuildTag::factory()->withoutPhase()->create()))->handle($this->makeConnector());

        Saloon::assertNothingSent();
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'no game version with a Warcraft Logs namespace')
        );
    }

    #[Test]
    public function it_skips_a_guild_tag_whose_game_version_has_no_namespace(): void
    {
        Saloon::fake([]);

        (new FetchReportsByGuildTag($this->fetchableGuildTag(namespace: null)))->handle($this->makeConnector());

        Saloon::assertNothingSent();
    }

    // ==================== rate limiting ====================

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_until_the_points_reset_when_rate_limited_mid_pagination(): void
    {
        $this->freezeTime();
        $this->app->make(RateLimitResetCache::class)->put(
            new RateLimitData(limitPerHour: 3600, pointsSpentThisHour: 3600.0, pointsResetIn: 1200),
        );

        $guildTag = $this->fetchableGuildTag();
        $firstPage = [$this->reportPayload('PAGE01', 'First', Carbon::parse('2025-01-01 19:00:00'), Carbon::parse('2025-01-01 22:00:00'), guildTagId: $guildTag->id)];

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => function (PendingRequest $pendingRequest) use ($firstPage): MockResponse {
                if ((int) data_get($pendingRequest->body()->all(), 'variables.page', 1) === 1) {
                    return MockResponse::make($this->reportsPage($firstPage, page: 1, hasMorePages: true));
                }

                return MockResponse::make(['error' => 'Too Many Requests'], 429);
            },
        ]);

        $job = (new FetchReportsByGuildTag($guildTag))->withFakeQueueInteractions();
        $job->handle($this->makeConnector());

        $job->assertReleased(delay: 1200);
        $job->assertNotFailed();
    }

    // ==================== batch cancellation ====================

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        Saloon::fake([]);

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $job = new FetchReportsByGuildTag($this->fetchableGuildTag());
        $job->batchId = $batch->id;
        dispatch_sync($job);

        Saloon::assertNothingSent();
    }

    // ==================== job contract ====================

    #[Test]
    #[Group('contract')]
    public function it_retries_for_two_hours_but_fails_on_its_first_exception_or_timeout(): void
    {
        $this->freezeTime();

        $job = new FetchReportsByGuildTag($this->fetchableGuildTag());
        $class = new ReflectionClass(FetchReportsByGuildTag::class);
        $maxExceptions = $class->getAttributes(MaxExceptions::class);

        $this->assertEquals(now()->addHours(2), $job->retryUntil());
        $this->assertCount(1, $maxExceptions);
        $this->assertSame(1, $maxExceptions[0]->newInstance()->maxExceptions);
        $this->assertCount(1, $class->getAttributes(FailOnTimeout::class));
    }

    #[Test]
    #[Group('contract')]
    public function it_tags_the_job_with_its_guild_tag_and_game_version(): void
    {
        $guildTag = $this->fetchableGuildTag();
        $gameVersionId = $guildTag->phase->game_version_id;

        $this->assertSame(
            ['warcraftlogs', 'reports', "guild-tag:{$guildTag->id}", "game-version:{$gameVersionId}"],
            (new FetchReportsByGuildTag($guildTag))->tags(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_omits_the_game_version_tag_when_the_chain_is_incomplete(): void
    {
        $guildTag = GuildTag::factory()->withoutPhase()->create();

        $this->assertSame(
            ['warcraftlogs', 'reports', "guild-tag:{$guildTag->id}"],
            (new FetchReportsByGuildTag($guildTag))->tags(),
        );
    }

    // ==================== report linking — same raid day ====================

    #[Test]
    public function it_links_reports_that_fall_on_the_same_raid_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $r1 = Report::where('code', 'AAA111')->first();
        $r2 = Report::where('code', 'BBB222')->first();
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r1->id, 'report_2' => $r2->id, 'created_by' => null]);
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r2->id, 'report_2' => $r1->id, 'created_by' => null]);
    }

    #[Test]
    public function it_does_not_link_reports_on_different_raid_days(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $this->assertDatabaseCount('pivot_report_links', 0);
    }

    #[Test]
    public function it_respects_the_0500_cutoff_when_linking(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        // 19:00 on Jan 15 and 03:00 on Jan 16 both fall within the same raid day (Jan 15, 05:00–Jan 16, 04:59)
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-16 03:00:00', 'Europe/Paris'), Carbon::parse('2025-01-16 04:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $r1 = Report::where('code', 'AAA111')->first();
        $r2 = Report::where('code', 'BBB222')->first();
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r1->id, 'report_2' => $r2->id]);
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r2->id, 'report_2' => $r1->id]);
    }

    #[Test]
    public function it_does_not_link_report_after_0500_to_previous_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        // 06:00 on Jan 16 is a new raid day, separate from Jan 15
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-16 06:00:00', 'Europe/Paris'), Carbon::parse('2025-01-16 08:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $this->assertDatabaseCount('pivot_report_links', 0);
    }

    #[Test]
    public function it_removes_stale_auto_links_when_reports_are_no_longer_on_the_same_raid_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        // Pre-create two reports already in the DB on different days
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        // Seed a stale auto-link that shouldn't exist
        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('pivot_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertDatabaseCount('pivot_report_links', 2);

        // Run the job again — the stale links should be removed
        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1, $report2]);

        $this->assertDatabaseCount('pivot_report_links', 0);
    }

    #[Test]
    public function it_does_not_override_manual_links_created_by_officers(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();
        $officer = User::factory()->officer()->create();

        // Two reports on different days — job would not auto-link them
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        // Officer manually creates a link between the two reports
        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('pivot_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the job again — the manual links must be preserved
        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1, $report2]);

        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => $officer->id]);
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => $officer->id]);
    }

    // ==================== touching ====================

    #[Test]
    public function it_touches_report_updated_at_when_auto_links_are_inserted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        $originalTime = now()->subHour();
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        // Backdate the reports after the first run to isolate the touch from the second run
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);

        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1, $report2]);

        // No new links inserted on second run, so timestamps must remain unchanged
        $this->assertEquals($originalTime->toDateTimeString(), Report::where('code', 'AAA111')->first()->updated_at->toDateTimeString());
        $this->assertEquals($originalTime->toDateTimeString(), Report::where('code', 'BBB222')->first()->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_touches_report_updated_at_when_stale_auto_links_are_deleted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        // Two reports on different days, so no auto-links should be created
        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        // Seed a stale auto-link
        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('pivot_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Backdate reports so we can observe the touch
        $originalTime = now()->subHour();
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);

        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1, $report2]);

        // Both reports referenced in the deleted link must be touched
        $this->assertGreaterThan($originalTime, Report::where('code', 'AAA111')->first()->updated_at);
        $this->assertGreaterThan($originalTime, Report::where('code', 'BBB222')->first()->updated_at);
    }

    #[Test]
    public function it_touches_report_updated_at_when_new_auto_links_are_inserted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        $report1Data = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2Data = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        // Persist both reports first so we can track their updated_at
        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1Data, $report2Data]);

        // Backdate to isolate the initial persist from our assertion
        $originalTime = now()->subHour();
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);
        DB::table('pivot_report_links')->truncate();

        // Run again — links should now be inserted and reports touched
        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1Data, $report2Data]);

        $this->assertGreaterThan($originalTime, Report::where('code', 'AAA111')->first()->updated_at);
        $this->assertGreaterThan($originalTime, Report::where('code', 'BBB222')->first()->updated_at);
    }

    #[Test]
    public function it_does_not_duplicate_existing_valid_auto_links(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = $this->fetchableGuildTag();

        $report1 = $this->reportPayload('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);
        $report2 = $this->reportPayload('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTagId: $guildTag->id);

        // Run once — creates links
        $job = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job, [$report1, $report2]);

        $this->assertDatabaseCount('pivot_report_links', 2);

        // Run again — must not insert duplicates
        $job2 = new FetchReportsByGuildTag($guildTag);
        $this->runJob($job2, [$report1, $report2]);

        $this->assertDatabaseCount('pivot_report_links', 2);
    }

    // ==================== helpers ====================

    private function fetchableGuildTag(?WarcraftLogsNamespace $namespace = WarcraftLogsNamespace::Anniversary): GuildTag
    {
        $gameVersion = GameVersion::factory()->create(['warcraftlogs_namespace' => $namespace]);
        $phase = Phase::factory()->forGameVersion($gameVersion)->create();

        return GuildTag::factory()->withPhase($phase)->create();
    }

    /**
     * Fake a single page of reports, then run the job.
     *
     * @param  array<int, array<string, mixed>>  $reports
     */
    private function runJob(FetchReportsByGuildTag $job, array $reports = []): void
    {
        $this->fakeReportPages([$reports]);

        $job->handle($this->makeConnector());
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $pages
     */
    private function fakeReportPages(array $pages): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => function (PendingRequest $pendingRequest) use ($pages): MockResponse {
                $page = (int) data_get($pendingRequest->body()->all(), 'variables.page', 1);

                return MockResponse::make($this->reportsPage($pages[$page - 1] ?? [], page: $page, hasMorePages: $page < count($pages)));
            },
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<string, mixed>
     */
    private function reportsPage(array $reports, int $page, bool $hasMorePages): array
    {
        return ['data' => ['reportData' => ['reports' => [
            'data' => $reports,
            'current_page' => $page,
            'has_more_pages' => $hasMorePages,
        ]]]];
    }

    /**
     * @param  array<string, mixed>|null  $zone
     * @return array<string, mixed>
     */
    private function reportPayload(string $code, string $title, Carbon $startTime, Carbon $endTime, ?int $guildTagId = null, ?array $zone = null): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'startTime' => $startTime->getTimestampMs(),
            'endTime' => $endTime->getTimestampMs(),
            'guildTag' => $guildTagId === null ? null : ['id' => $guildTagId, 'name' => "Tag {$guildTagId}"],
            'zone' => $zone,
        ];
    }
}
