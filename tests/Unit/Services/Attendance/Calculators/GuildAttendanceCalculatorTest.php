<?php

namespace Tests\Unit\Services\Attendance\Calculators;

use App\Services\Attendance\Aggregators\ReportsAggregator;
use App\Services\Attendance\Calculators\GuildAttendanceCalculator;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GuildAttendanceCalculatorTest extends TestCase
{
    protected function makeCalculator(): GuildAttendanceCalculator
    {
        return new GuildAttendanceCalculator(new ReportsAggregator);
    }

    protected function makeAttendance(string $code, Carbon $startTime, array $players): GuildAttendance
    {
        return new GuildAttendance(
            code: $code,
            startTime: $startTime,
            players: array_map(
                fn (array $p) => new PlayerAttendance(name: $p['name'], presence: $p['presence']),
                $players,
            ),
        );
    }

    // ==================== Empty Input Tests ====================

    public function test_calculate_returns_empty_collection_for_empty_input(): void
    {
        $result = $this->makeCalculator()->calculate([]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ==================== Presence Filtering Tests ====================

    public function test_calculate_does_not_count_presence_zero_as_attendance(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 0]]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_does_not_count_presence_outside_1_and_2(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 3]]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(50.0, $thrall->percentage);
    }

    public function test_calculate_counts_both_presence_1_and_2_as_attended(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 2]]),
            $this->makeAttendance('report3', Carbon::parse('2025-01-15'), [['name' => 'Thrall', 'presence' => 0]]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Thrall');

        $this->assertEquals(3, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(66.67, $thrall->percentage);
    }

    public function test_calculate_player_with_only_invalid_presence_has_zero_percent(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [['name' => 'Thrall', 'presence' => 0]]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [['name' => 'Thrall', 'presence' => 3]]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Thrall');

        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(0, $thrall->reportsAttended);
        $this->assertEquals(0.0, $thrall->percentage);
    }

    // ==================== Result Sorting Tests ====================

    public function test_calculate_results_are_sorted_alphabetically_by_name(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Zara', 'presence' => 1],
                ['name' => 'Alice', 'presence' => 1],
                ['name' => 'Milo', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $this->assertEquals('Alice', $stats[0]->name);
        $this->assertEquals('Milo', $stats[1]->name);
        $this->assertEquals('Zara', $stats[2]->name);
    }

    // ==================== Absent Player Tests ====================

    public function test_calculate_player_absent_after_first_appearance(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Jaina', 'presence' => 1],
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('report3', Carbon::parse('2025-01-15'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $jaina = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Jaina');

        $this->assertEquals(3, $jaina->totalReports);
        $this->assertEquals(1, $jaina->reportsAttended);
        $this->assertEquals(33.33, $jaina->percentage);
    }

    public function test_calculate_single_player_single_report(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-15'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->first();

        $this->assertEquals(1, $thrall->totalReports);
        $this->assertEquals(1, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    // ==================== First Attendance Date Tests ====================

    public function test_calculate_returns_correct_first_attendance_date(): void
    {
        $jan08 = Carbon::parse('2025-01-08');
        $jan15 = Carbon::parse('2025-01-15');

        // Unsorted: jan15 listed first — first attendance should still resolve to jan08
        $attendance = [
            $this->makeAttendance('report1', $jan15, [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', $jan08, [['name' => 'Thrall', 'presence' => 1]]),
        ];

        $thrall = $this->makeCalculator()->calculate($attendance)->firstWhere('name', 'Thrall');

        $this->assertTrue($thrall->firstAttendance->eq($jan08));
    }

    public function test_calculate_tracks_first_attendance_per_player(): void
    {
        $jan01 = Carbon::parse('2025-01-01');
        $jan08 = Carbon::parse('2025-01-08');

        $attendance = [
            $this->makeAttendance('report1', $jan01, [['name' => 'Thrall', 'presence' => 1]]),
            $this->makeAttendance('report2', $jan08, [
                ['name' => 'Thrall', 'presence' => 1],
                ['name' => 'Jaina', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $thrall = $stats->firstWhere('name', 'Thrall');
        $jaina = $stats->firstWhere('name', 'Jaina');

        $this->assertTrue($thrall->firstAttendance->eq($jan01));
        $this->assertTrue($jaina->firstAttendance->eq($jan08));

        // Jaina joined on report 2, so she only has 1 total report
        $this->assertEquals(1, $jaina->totalReports);
        // Thrall was there from report 1, so he has 2 total reports
        $this->assertEquals(2, $thrall->totalReports);
    }

    // ==================== Multiple Players Tests ====================

    public function test_calculate_handles_multiple_players(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Thrall', 'presence' => 1],
                ['name' => 'Jaina', 'presence' => 1],
                ['name' => 'Sylvanas', 'presence' => 2],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $this->assertCount(3, $stats);
        $this->assertNotNull($stats->firstWhere('name', 'Thrall'));
        $this->assertNotNull($stats->firstWhere('name', 'Jaina'));
        $this->assertNotNull($stats->firstWhere('name', 'Sylvanas'));
    }

    // ==================== Returns PlayerAttendanceStats Tests ====================

    public function test_calculate_returns_player_attendance_stats_objects(): void
    {
        $attendance = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $this->assertInstanceOf(PlayerAttendanceStats::class, $stats->first());
    }

    // ==================== Same-Day Raid Merging Tests ====================

    public function test_calculate_merges_same_day_raids_into_single_record(): void
    {
        // Two raids on the same evening — Fizzywigs in one, not the other
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Fizzywigs', 'presence' => 1],
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
                ['name' => 'Jaina', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        // Should be 1 total report (merged day), all players attended
        $this->assertEquals(1, $stats->firstWhere('name', 'Fizzywigs')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Fizzywigs')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Fizzywigs')->percentage);

        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->totalReports);
    }

    public function test_calculate_raid_before_0500_belongs_to_previous_day(): void
    {
        // 03:00 on Jan 16 should be part of the Jan 15 raid day
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-16 03:00', 'Europe/Paris'), [
                ['name' => 'Jaina', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        // Both raids merge into one raid day
        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Jaina')->totalReports);
    }

    public function test_calculate_raid_after_0500_belongs_to_current_day(): void
    {
        // 06:00 on Jan 16 should be a new raid day, separate from Jan 15
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-16 06:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        // Two separate raid days
        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->totalReports);
    }

    public function test_calculate_different_days_remain_separate(): void
    {
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-22 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->totalReports);
        $this->assertEquals(2, $stats->firstWhere('name', 'Thrall')->reportsAttended);
    }

    public function test_calculate_merge_keeps_best_presence_value(): void
    {
        // Player has presence 0 in one raid, presence 1 in another — should count as attended
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 0],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Thrall')->percentage);
    }

    public function test_calculate_merge_prefers_present_over_benched(): void
    {
        // Presence 1 (present) should be preferred over 2 (benched)
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 2],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        // Both 1 and 2 count as attended, so either way it's 100%
        $this->assertEquals(1, $stats->firstWhere('name', 'Thrall')->reportsAttended);
        $this->assertEquals(100.0, $stats->firstWhere('name', 'Thrall')->percentage);
    }

    public function test_calculate_merge_three_raids_same_day(): void
    {
        $attendance = [
            $this->makeAttendance('raid1', Carbon::parse('2025-01-15 19:00', 'Europe/Paris'), [
                ['name' => 'Alice', 'presence' => 1],
            ]),
            $this->makeAttendance('raid2', Carbon::parse('2025-01-15 19:30', 'Europe/Paris'), [
                ['name' => 'Bob', 'presence' => 1],
            ]),
            $this->makeAttendance('raid3', Carbon::parse('2025-01-15 20:00', 'Europe/Paris'), [
                ['name' => 'Charlie', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculate($attendance);

        // All three raids merge into one day — 3 players, 1 report each
        $this->assertCount(3, $stats);
        $this->assertEquals(1, $stats->firstWhere('name', 'Alice')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Bob')->totalReports);
        $this->assertEquals(1, $stats->firstWhere('name', 'Charlie')->totalReports);
    }

    // ==================== Calculate And Aggregate Tests ====================

    public function test_calculate_and_aggregate_returns_empty_for_empty_input(): void
    {
        $result = $this->makeCalculator()->calculateAndAggregate([]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_calculate_and_aggregate_combines_multiple_sources(): void
    {
        $source1 = [
            $this->makeAttendance('report1', Carbon::parse('2025-01-01'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];
        $source2 = [
            $this->makeAttendance('report2', Carbon::parse('2025-01-08'), [
                ['name' => 'Thrall', 'presence' => 1],
            ]),
        ];

        $stats = $this->makeCalculator()->calculateAndAggregate([$source1, $source2]);

        $thrall = $stats->firstWhere('name', 'Thrall');
        $this->assertEquals(2, $thrall->totalReports);
        $this->assertEquals(2, $thrall->reportsAttended);
        $this->assertEquals(100.0, $thrall->percentage);
    }

    public function test_calculate_and_aggregate_keeps_earliest_first_attendance(): void
    {
        $jan01 = Carbon::parse('2025-01-01');
        $jan15 = Carbon::parse('2025-01-15');

        $source1 = [
            $this->makeAttendance('report1', $jan15, [['name' => 'Thrall', 'presence' => 1]]),
        ];
        $source2 = [
            $this->makeAttendance('report2', $jan01, [['name' => 'Thrall', 'presence' => 1]]),
        ];

        $stats = $this->makeCalculator()->calculateAndAggregate([$source1, $source2]);

        $thrall = $stats->first();
        $this->assertTrue($thrall->firstAttendance->eq($jan01));
    }
}
