<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\Raids\Report;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchWarcraftLogsAttendanceDataTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    #[Test]
    public function it_creates_pivot_entries_for_characters_with_count_attendance_ranks(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'abc123']);

        $guildAttendance = new GuildAttendance(
            code: 'abc123',
            players: [new PlayerAttendance(name: 'Thrall', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

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
        $report = Report::factory()->create(['code' => 'bench001']);

        $guildAttendance = new GuildAttendance(
            code: 'bench001',
            players: [new PlayerAttendance(name: 'Jaina', presence: 2)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 2,
        ]);
    }

    // ==========================================
    // Rank Filtering
    // ==========================================

    #[Test]
    public function it_skips_characters_whose_ranks_do_not_count_attendance(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();
        $character = Character::factory()->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'skp001']);

        $guildAttendance = new GuildAttendance(
            code: 'skp001',
            players: [new PlayerAttendance(name: 'Sylvanas', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_skips_characters_with_no_rank(): void
    {
        $character = Character::factory()->create(['name' => 'Illidan', 'rank_id' => null]);
        $report = Report::factory()->create(['code' => 'norank1']);

        $guildAttendance = new GuildAttendance(
            code: 'norank1',
            players: [new PlayerAttendance(name: 'Illidan', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    // ==========================================
    // Missing Records
    // ==========================================

    #[Test]
    public function it_skips_players_not_found_in_the_database(): void
    {
        $report = Report::factory()->create(['code' => 'unk001']);

        $guildAttendance = new GuildAttendance(
            code: 'unk001',
            players: [new PlayerAttendance(name: 'UnknownPlayer', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    #[Test]
    public function it_skips_attendance_records_for_reports_not_in_the_database(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);

        // No report created — simulates attendance for a report not in the DB
        $guildAttendance = new GuildAttendance(
            code: 'missing1',
            players: [new PlayerAttendance(name: 'Arthas', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseCount('pivot_characters_raid_reports', 0);
    }

    // ==========================================
    // Touching
    // ==========================================

    #[Test]
    public function it_touches_the_report_updated_at_when_attendance_is_synced(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $originalTime = now()->subHour();
        $report = Report::factory()->create(['code' => 'touch01', 'updated_at' => $originalTime]);

        $guildAttendance = new GuildAttendance(
            code: 'touch01',
            players: [new PlayerAttendance(name: 'Thrall', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertGreaterThan($originalTime, $report->fresh()->updated_at);
    }

    #[Test]
    public function it_does_not_touch_the_report_when_no_attendance_data_is_synced(): void
    {
        $originalTime = now()->subHour();
        $report = Report::factory()->create(['code' => 'notouch1', 'updated_at' => $originalTime]);

        $guildAttendance = new GuildAttendance(
            code: 'notouch1',
            players: [],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertEquals($originalTime->toDateTimeString(), $report->fresh()->updated_at->toDateTimeString());
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    #[Test]
    public function it_handles_duplicate_character_entries_without_throwing(): void
    {
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Rexxar', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'dup001']);

        $guildAttendance = new GuildAttendance(
            code: 'dup001',
            players: [new PlayerAttendance(name: 'Rexxar', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->twice()->andReturn(LazyCollection::make([$guildAttendance]));

        // Run the job twice to simulate concurrent execution or a re-run
        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);
        $job->handle($attendanceService);

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
        $report = Report::factory()->create(['code' => 'exists1']);

        $existsRecord = new GuildAttendance(
            code: 'exists1',
            players: [new PlayerAttendance(name: 'Varian', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $missingRecord = new GuildAttendance(
            code: 'notindb1',
            players: [new PlayerAttendance(name: 'Varian', presence: 1)],
            startTime: Carbon::parse('2025-06-02'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$existsRecord, $missingRecord]));

        $job = new FetchWarcraftLogsAttendanceData;
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['raid_report_id' => $report->id]);
        $this->assertDatabaseCount('pivot_characters_raid_reports', 1);
    }

    #[Test]
    public function it_skips_execution_when_batch_is_cancelled(): void
    {
        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldNotReceive('lazy');
        $this->app->instance(Attendance::class, $attendanceService);

        $job = new FetchWarcraftLogsAttendanceData;
        $job->batchId = $batch->id;
        dispatch_sync($job);
    }
}
