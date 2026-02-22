<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\Reports;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class FetchWarcraftLogsReportsByGuildTagTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    public function test_it_persists_reports_for_the_given_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create();

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')
            ->once()
            ->with(Mockery::on(fn ($collection) => $collection->contains($guildTag)))
            ->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('toDatabase')->once()->andReturn(collect());

        $job = new FetchWarcraftLogsReportsByGuildTag($guildTag);
        $job->handle($reportsService);
    }

    // ==========================================
    // Time Filters
    // ==========================================

    public function test_it_applies_the_since_time_filter(): void
    {
        $guildTag = GuildTag::factory()->create();
        $since = Carbon::parse('2025-01-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with($since)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('toDatabase')->once()->andReturn(collect());

        $job = new FetchWarcraftLogsReportsByGuildTag($guildTag, since: $since);
        $job->handle($reportsService);
    }

    public function test_it_applies_the_before_time_filter(): void
    {
        $guildTag = GuildTag::factory()->create();
        $before = Carbon::parse('2025-06-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with(null)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with($before)->andReturnSelf();
        $reportsService->shouldReceive('toDatabase')->once()->andReturn(collect());

        $job = new FetchWarcraftLogsReportsByGuildTag($guildTag, before: $before);
        $job->handle($reportsService);
    }

    public function test_it_applies_both_time_filters(): void
    {
        $guildTag = GuildTag::factory()->create();
        $since = Carbon::parse('2025-01-01');
        $before = Carbon::parse('2025-06-01');

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldReceive('byGuildTags')->once()->andReturnSelf();
        $reportsService->shouldReceive('startTime')->once()->with($since)->andReturnSelf();
        $reportsService->shouldReceive('endTime')->once()->with($before)->andReturnSelf();
        $reportsService->shouldReceive('toDatabase')->once()->andReturn(collect());

        $job = new FetchWarcraftLogsReportsByGuildTag($guildTag, since: $since, before: $before);
        $job->handle($reportsService);
    }

    // ==========================================
    // Batch Cancellation
    // ==========================================

    public function test_it_skips_execution_when_batch_is_cancelled(): void
    {
        $guildTag = GuildTag::factory()->create();

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $reportsService = Mockery::mock(Reports::class);
        $reportsService->shouldNotReceive('byGuildTags');
        $reportsService->shouldNotReceive('toDatabase');

        $job = new FetchWarcraftLogsReportsByGuildTag($guildTag);
        $job->batchId = $batch->id;
        $job->handle($reportsService);
    }
}
