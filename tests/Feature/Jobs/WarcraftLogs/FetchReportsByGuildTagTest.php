<?php

namespace Tests\Feature\Jobs\WarcraftLogs;

use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\GuildTag;
use App\Services\WarcraftLogs\Reports;
use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
use App\Services\WarcraftLogs\ValueObjects\ReportData;
use App\Services\WarcraftLogs\ValueObjects\ZoneData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchReportsByGuildTagTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    #[Test]
    public function it_persists_reports_for_the_given_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create();

        $report = new ReportData(
            code: 'ABC123',
            title: 'Test Report',
            startTime: Carbon::parse('2025-01-01 19:00:00'),
            endTime: Carbon::parse('2025-01-01 22:00:00'),
        );

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')
            ->once()
            ->with(Mockery::on(fn ($collection) => $collection->contains($guildTag)))
            ->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect([$report]));

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($reportsService);

        $this->assertDatabaseHas('raid_reports', ['code' => 'ABC123', 'title' => 'Test Report']);
    }

    #[Test]
    public function it_upserts_the_zone_when_persisting_a_report_with_a_zone(): void
    {
        $guildTag = GuildTag::factory()->create();

        $zone = new ZoneData(
            id: 2000,
            name: 'Karazhan',
            difficulties: [new DifficultyData(id: 3, name: 'Normal', sizes: [10])],
            expansion: new ExpansionData(id: 1001, name: 'TBC'),
        );

        $report = new ReportData(
            code: 'ZONE01',
            title: 'Zone Test Report',
            startTime: Carbon::parse('2025-01-01 19:00:00'),
            endTime: Carbon::parse('2025-01-01 22:00:00'),
            guildTag: $guildTag,
            zone: $zone,
        );

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect([$report]));

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($reportsService);

        $this->assertDatabaseHas('wcl_zones', ['id' => 2000, 'name' => 'Karazhan']);
        $this->assertDatabaseHas('raid_reports', ['code' => 'ZONE01', 'zone_id' => 2000]);
    }

    // ==========================================
    // Time Filters
    // ==========================================

    #[Test]
    public function it_applies_the_since_time_filter(): void
    {
        $guildTag = GuildTag::factory()->create();
        $since = Carbon::parse('2025-01-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with($since)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect());

        $job = new FetchReportsByGuildTag($guildTag, since: $since);
        $job->handle($reportsService);
    }

    #[Test]
    public function it_applies_the_before_time_filter(): void
    {
        $guildTag = GuildTag::factory()->create();
        $before = Carbon::parse('2025-06-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with($before)->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect());

        $job = new FetchReportsByGuildTag($guildTag, before: $before);
        $job->handle($reportsService);
    }

    #[Test]
    public function it_applies_both_time_filters(): void
    {
        $guildTag = GuildTag::factory()->create();
        $since = Carbon::parse('2025-01-01');
        $before = Carbon::parse('2025-06-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with($since)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with($before)->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect());

        $job = new FetchReportsByGuildTag($guildTag, since: $since, before: $before);
        $job->handle($reportsService);
    }

    // ==========================================
    // Batch Cancellation
    // ==========================================

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        $guildTag = GuildTag::factory()->create();

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldNotReceive('byGuildTags');
        $reportsService->shouldNotReceive('get');
        $this->app->instance(Reports::class, $reportsService);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->batchId = $batch->id;
        dispatch_sync($job);
    }

    // ==========================================
    // Report Linking — Same Raid Day
    // ==========================================

    protected function mockReportsService(array $reports): Reports
    {
        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->andReturnSelf();
        $reportsService->shouldReceive('get')->once()->andReturn(collect($reports));

        return $reportsService;
    }

    #[Test]
    public function it_links_reports_that_fall_on_the_same_raid_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $r1 = Report::where('code', 'AAA111')->first();
        $r2 = Report::where('code', 'BBB222')->first();
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r1->id, 'report_2' => $r2->id, 'created_by' => null]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r2->id, 'report_2' => $r1->id, 'created_by' => null]);
    }

    #[Test]
    public function it_does_not_link_reports_on_different_raid_days(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseCount('raid_report_links', 0);
    }

    #[Test]
    public function it_respects_the_0500_cutoff_when_linking(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        // 19:00 on Jan 15 and 03:00 on Jan 16 both fall within the same raid day (Jan 15, 05:00–Jan 16, 04:59)
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-16 03:00:00', 'Europe/Paris'), Carbon::parse('2025-01-16 04:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $r1 = Report::where('code', 'AAA111')->first();
        $r2 = Report::where('code', 'BBB222')->first();
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r1->id, 'report_2' => $r2->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r2->id, 'report_2' => $r1->id]);
    }

    #[Test]
    public function it_does_not_link_report_after_0500_to_previous_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        // 06:00 on Jan 16 is a new raid day, separate from Jan 15
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-16 06:00:00', 'Europe/Paris'), Carbon::parse('2025-01-16 08:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseCount('raid_report_links', 0);
    }

    #[Test]
    public function it_removes_stale_auto_links_when_reports_are_no_longer_on_the_same_raid_day(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        // Pre-create two reports already in the DB on different days
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTag: $guildTag);

        // Seed a stale auto-link that shouldn't exist
        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('raid_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertDatabaseCount('raid_report_links', 2);

        // Run the job again — the stale links should be removed
        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseCount('raid_report_links', 0);
    }

    #[Test]
    public function it_does_not_override_manual_links_created_by_officers(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();
        $officer = User::factory()->officer()->create();

        // Two reports on different days — job would not auto-link them
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        // Officer manually creates a link between the two reports
        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('raid_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the job again — the manual links must be preserved
        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => $officer->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $r2Id, 'report_2' => $r1Id, 'created_by' => $officer->id]);
    }

    // ==========================================
    // Touching
    // ==========================================

    #[Test]
    public function it_touches_report_updated_at_when_auto_links_are_inserted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        $originalTime = now()->subHour();
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        // Backdate the reports after the first run to isolate the touch from the second run
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);

        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1, $report2]));

        // No new links inserted on second run, so timestamps must remain unchanged
        $this->assertEquals($originalTime->toDateTimeString(), Report::where('code', 'AAA111')->first()->updated_at->toDateTimeString());
        $this->assertEquals($originalTime->toDateTimeString(), Report::where('code', 'BBB222')->first()->updated_at->toDateTimeString());
    }

    #[Test]
    public function it_touches_report_updated_at_when_stale_auto_links_are_deleted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        // Two reports on different days, so no auto-links should be created
        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-22 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-22 22:00:00', 'Europe/Paris'), guildTag: $guildTag);

        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        // Seed a stale auto-link
        $r1Id = Report::where('code', 'AAA111')->value('id');
        $r2Id = Report::where('code', 'BBB222')->value('id');
        DB::table('raid_report_links')->insert([
            ['report_1' => $r1Id, 'report_2' => $r2Id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Backdate reports so we can observe the touch
        $originalTime = now()->subHour();
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);

        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1, $report2]));

        // Both reports referenced in the deleted link must be touched
        $this->assertGreaterThan($originalTime, Report::where('code', 'AAA111')->first()->updated_at);
        $this->assertGreaterThan($originalTime, Report::where('code', 'BBB222')->first()->updated_at);
    }

    #[Test]
    public function it_touches_report_updated_at_when_new_auto_links_are_inserted(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        $report1Data = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2Data = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTag: $guildTag);

        // Persist both reports first so we can track their updated_at
        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1Data, $report2Data]));

        // Backdate to isolate the initial persist from our assertion
        $originalTime = now()->subHour();
        Report::whereIn('code', ['AAA111', 'BBB222'])->update(['updated_at' => $originalTime]);
        DB::table('raid_report_links')->truncate();

        // Run again — links should now be inserted and reports touched
        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1Data, $report2Data]));

        $this->assertGreaterThan($originalTime, Report::where('code', 'AAA111')->first()->updated_at);
        $this->assertGreaterThan($originalTime, Report::where('code', 'BBB222')->first()->updated_at);
    }

    #[Test]
    public function it_does_not_duplicate_existing_valid_auto_links(): void
    {
        config(['app.timezone' => 'Europe/Paris']);

        $guildTag = GuildTag::factory()->create();

        $report1 = new ReportData('AAA111', 'Report 1', Carbon::parse('2025-01-15 19:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 22:00:00', 'Europe/Paris'), guildTag: $guildTag);
        $report2 = new ReportData('BBB222', 'Report 2', Carbon::parse('2025-01-15 20:00:00', 'Europe/Paris'), Carbon::parse('2025-01-15 23:00:00', 'Europe/Paris'), guildTag: $guildTag);

        // Run once — creates links
        $job = new FetchReportsByGuildTag($guildTag);
        $job->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseCount('raid_report_links', 2);

        // Run again — must not insert duplicates
        $job2 = new FetchReportsByGuildTag($guildTag);
        $job2->handle($this->mockReportsService([$report1, $report2]));

        $this->assertDatabaseCount('raid_report_links', 2);
    }
}
