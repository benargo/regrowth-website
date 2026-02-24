<?php

namespace Tests\Feature\Jobs\RegrowthAddon\Export;

use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BuildPlayerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    // ==========================================
    // Job Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildPlayerAttendance);
    }

    public function test_it_uses_batchable_trait(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(BuildPlayerAttendance::class));
    }

    public function test_it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildPlayerAttendance)->tags());
    }

    public function test_it_has_skip_if_batch_cancelled_middleware(): void
    {
        $middlewareClasses = array_map(fn ($m) => get_class($m), (new BuildPlayerAttendance)->middleware());

        $this->assertContains(SkipIfBatchCancelled::class, $middlewareClasses);
    }

    // ==========================================
    // Empty Data Tests
    // ==========================================

    public function test_it_caches_empty_collection_when_no_ranks_count_attendance(): void
    {
        GuildRank::factory()->doesNotCountAttendance()->create();

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $this->assertNotNull($cached);
        $this->assertEmpty($cached);
    }

    public function test_it_caches_empty_collection_when_no_ranks_exist(): void
    {
        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $this->assertNotNull($cached);
        $this->assertEmpty($cached);
    }

    public function test_it_caches_empty_collection_when_no_qualifying_reports_exist(): void
    {
        $rank = GuildRank::factory()->create();
        Character::factory()->create(['rank_id' => $rank->id]);
        GuildTag::factory()->doesNotCountAttendance()->create();

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $this->assertNotNull($cached);
        $this->assertEmpty($cached);
    }

    // ==========================================
    // Attendance Data Tests
    // ==========================================

    public function test_it_builds_attendance_from_database_pivot(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $this->assertCount(1, $cached);
        $playerData = collect($cached)->firstWhere('name', 'TestPlayer');
        $this->assertNotNull($playerData);
        $this->assertEquals(1, $playerData['attendance']['attended']);
        $this->assertEquals(1, $playerData['attendance']['total']);
        $this->assertEquals(100.0, $playerData['attendance']['percentage']);
    }

    public function test_it_maps_character_id_from_model(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::now()->subDays(1)]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $playerData = collect($cached)->firstWhere('name', 'TestPlayer');
        $this->assertEquals($character->id, $playerData['id']);
    }

    public function test_it_includes_first_attendance_date(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $playerData = collect($cached)->firstWhere('name', 'TestPlayer');
        $this->assertNotNull($playerData['attendance']['first_attendance']);
    }

    public function test_it_excludes_characters_from_non_counting_ranks(): void
    {
        $countingRank = GuildRank::factory()->create();
        $nonCountingRank = GuildRank::factory()->doesNotCountAttendance()->create();
        $countingChar = Character::factory()->create(['name' => 'CountingPlayer', 'rank_id' => $countingRank->id]);
        $nonCountingChar = Character::factory()->create(['name' => 'NonCountingPlayer', 'rank_id' => $nonCountingRank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::now()->subDays(1)]);
        $report->characters()->attach($countingChar->id, ['presence' => 1]);
        $report->characters()->attach($nonCountingChar->id, ['presence' => 1]);

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        $names = collect($cached)->pluck('name')->toArray();
        $this->assertContains('CountingPlayer', $names);
        $this->assertNotContains('NonCountingPlayer', $names);
    }

    public function test_it_excludes_reports_from_non_counting_guild_tags(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'TestPlayer', 'rank_id' => $rank->id]);
        $countingTag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $nonCountingTag = GuildTag::factory()->doesNotCountAttendance()->withoutPhase()->create();
        $countingReport = Report::factory()->withGuildTag($countingTag)->create(['start_time' => Carbon::parse('2025-01-15 20:00:00')]);
        $nonCountingReport = Report::factory()->withGuildTag($nonCountingTag)->create(['start_time' => Carbon::parse('2025-01-22 20:00:00')]);
        $countingReport->characters()->attach($character->id, ['presence' => 1]);
        $nonCountingReport->characters()->attach($character->id, ['presence' => 1]);

        app(BuildPlayerAttendance::class)->handle(app(\App\Services\AttendanceCalculator\AttendanceCalculator::class));

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.attendance');

        // Only one counting report should be included
        $playerData = collect($cached)->firstWhere('name', 'TestPlayer');
        $this->assertEquals(1, $playerData['attendance']['total']);
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildPlayerAttendance job failed.'
                    && isset($context['error']);
            });

        $job = new BuildPlayerAttendance;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
