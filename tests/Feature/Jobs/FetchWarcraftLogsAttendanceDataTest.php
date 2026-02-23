<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\EmptyCollectionException;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;
use Mockery;
use Tests\TestCase;

class FetchWarcraftLogsAttendanceDataTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    public function test_it_creates_pivot_entries_for_characters_with_count_attendance_ranks(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'abc123']);

        $guildAttendance = new GuildAttendance(
            code: 'abc123',
            players: [new PlayerAttendance(name: 'Thrall', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->with([$guildTag->id])->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', [
            'character_id' => $character->id,
            'wcl_report_code' => 'abc123',
            'presence' => 1,
        ]);
    }

    public function test_it_stores_the_correct_presence_value(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'bench001']);

        $guildAttendance = new GuildAttendance(
            code: 'bench001',
            players: [new PlayerAttendance(name: 'Jaina', presence: 2)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', [
            'character_id' => $character->id,
            'wcl_report_code' => 'bench001',
            'presence' => 2,
        ]);
    }

    // ==========================================
    // Rank Filtering
    // ==========================================

    public function test_it_skips_characters_whose_ranks_do_not_count_attendance(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();
        $character = Character::factory()->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);
        $report = Report::factory()->create(['code' => 'skp001']);

        $guildAttendance = new GuildAttendance(
            code: 'skp001',
            players: [new PlayerAttendance(name: 'Sylvanas', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseMissing('pivot_characters_wcl_reports', [
            'character_id' => $character->id,
            'wcl_report_code' => 'skp001',
        ]);
    }

    public function test_it_skips_characters_with_no_rank(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $character = Character::factory()->create(['name' => 'Illidan', 'rank_id' => null]);
        $report = Report::factory()->create(['code' => 'norank1']);

        $guildAttendance = new GuildAttendance(
            code: 'norank1',
            players: [new PlayerAttendance(name: 'Illidan', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseMissing('pivot_characters_wcl_reports', [
            'character_id' => $character->id,
        ]);
    }

    // ==========================================
    // Missing Records
    // ==========================================

    public function test_it_skips_players_not_found_in_the_database(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $report = Report::factory()->create(['code' => 'unk001']);

        $guildAttendance = new GuildAttendance(
            code: 'unk001',
            players: [new PlayerAttendance(name: 'UnknownPlayer', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseCount('pivot_characters_wcl_reports', 0);
    }

    public function test_it_skips_reports_not_found_in_the_database(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);

        $guildAttendance = new GuildAttendance(
            code: 'missing1',
            players: [new PlayerAttendance(name: 'Arthas', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$guildAttendance]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->handle($attendanceService);

        $this->assertDatabaseCount('pivot_characters_wcl_reports', 0);
    }

    // ==========================================
    // Date Filters
    // ==========================================

    public function test_it_filters_attendance_records_after_since_date(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Varian', 'rank_id' => $rank->id]);
        $includedReport = Report::factory()->create(['code' => 'incl001']);
        $excludedReport = Report::factory()->create(['code' => 'excl001']);

        $since = Carbon::parse('2025-06-01');

        $beforeRecord = new GuildAttendance(
            code: 'excl001',
            players: [new PlayerAttendance(name: 'Varian', presence: 1)],
            startTime: Carbon::parse('2025-05-31'),
        );

        $afterRecord = new GuildAttendance(
            code: 'incl001',
            players: [new PlayerAttendance(name: 'Varian', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$beforeRecord, $afterRecord]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]), since: $since);
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', ['wcl_report_code' => 'incl001']);
        $this->assertDatabaseMissing('pivot_characters_wcl_reports', ['wcl_report_code' => 'excl001']);
    }

    public function test_it_filters_attendance_records_before_before_date(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Anduin', 'rank_id' => $rank->id]);
        $includedReport = Report::factory()->create(['code' => 'incl002']);
        $excludedReport = Report::factory()->create(['code' => 'excl002']);

        $before = Carbon::parse('2025-06-01');

        $beforeRecord = new GuildAttendance(
            code: 'incl002',
            players: [new PlayerAttendance(name: 'Anduin', presence: 1)],
            startTime: Carbon::parse('2025-06-01'),
        );

        $afterRecord = new GuildAttendance(
            code: 'excl002',
            players: [new PlayerAttendance(name: 'Anduin', presence: 1)],
            startTime: Carbon::parse('2025-06-02'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$beforeRecord, $afterRecord]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]), before: $before);
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', ['wcl_report_code' => 'incl002']);
        $this->assertDatabaseMissing('pivot_characters_wcl_reports', ['wcl_report_code' => 'excl002']);
    }

    public function test_it_applies_both_date_filters(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);
        $character = Character::factory()->create(['name' => 'Garrosh', 'rank_id' => $rank->id]);

        Report::factory()->create(['code' => 'tooold1']);
        Report::factory()->create(['code' => 'inrange1']);
        Report::factory()->create(['code' => 'toonew1']);

        $since = Carbon::parse('2025-06-01');
        $before = Carbon::parse('2025-06-30');

        $tooOld = new GuildAttendance(
            code: 'tooold1',
            players: [new PlayerAttendance(name: 'Garrosh', presence: 1)],
            startTime: Carbon::parse('2025-05-31'),
        );

        $inRange = new GuildAttendance(
            code: 'inrange1',
            players: [new PlayerAttendance(name: 'Garrosh', presence: 1)],
            startTime: Carbon::parse('2025-06-15'),
        );

        $tooNew = new GuildAttendance(
            code: 'toonew1',
            players: [new PlayerAttendance(name: 'Garrosh', presence: 1)],
            startTime: Carbon::parse('2025-07-01'),
        );

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldReceive('tags')->once()->andReturnSelf();
        $attendanceService->shouldReceive('lazy')->once()->andReturn(LazyCollection::make([$tooOld, $inRange, $tooNew]));

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]), since: $since, before: $before);
        $job->handle($attendanceService);

        $this->assertDatabaseHas('pivot_characters_wcl_reports', ['wcl_report_code' => 'inrange1']);
        $this->assertDatabaseMissing('pivot_characters_wcl_reports', ['wcl_report_code' => 'tooold1']);
        $this->assertDatabaseMissing('pivot_characters_wcl_reports', ['wcl_report_code' => 'toonew1']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function test_it_throws_an_exception_when_guild_tags_are_empty(): void
    {
        $this->expectException(EmptyCollectionException::class);

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldNotReceive('tags');

        $job = new FetchWarcraftLogsAttendanceData(collect());
        $job->handle($attendanceService);
    }

    public function test_it_skips_execution_when_batch_is_cancelled(): void
    {
        $guildTag = GuildTag::factory()->countsAttendance()->create();

        $batch = Bus::batch([])->dispatch();
        $batch->cancel();

        $attendanceService = Mockery::mock(Attendance::class);
        $attendanceService->shouldNotReceive('tags');
        $attendanceService->shouldNotReceive('lazy');

        $job = new FetchWarcraftLogsAttendanceData(collect([$guildTag]));
        $job->batchId = $batch->id;
        $job->handle($attendanceService);
    }
}
