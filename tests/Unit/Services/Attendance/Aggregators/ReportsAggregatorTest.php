<?php

namespace Tests\Unit\Services\Attendance\Aggregators;

use App\Services\Attendance\Aggregators\ReportsAggregator;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReportsAggregatorTest extends TestCase
{
    protected function makeStats(
        string $name,
        Carbon $firstAttendance,
        int $totalReports,
        int $reportsAttended,
    ): PlayerAttendanceStats {
        $percentage = $totalReports > 0
            ? round(($reportsAttended / $totalReports) * 100, 2)
            : 0.0;

        return new PlayerAttendanceStats(
            name: $name,
            firstAttendance: $firstAttendance,
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            percentage: $percentage,
        );
    }

    // ==================== Empty Input Tests ====================

    public function test_aggregate_returns_empty_collection_for_empty_input(): void
    {
        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect());

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_aggregate_returns_empty_collection_when_all_sets_are_empty(): void
    {
        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([collect(), collect()]));

        $this->assertTrue($result->isEmpty());
    }

    // ==================== Single Set Tests ====================

    public function test_aggregate_returns_stats_unchanged_for_single_set(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $stats = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$stats]));

        $this->assertCount(1, $result);

        $thrall = $result->first();
        $this->assertEquals('Thrall', $thrall->name);
        $this->assertTrue($thrall->firstAttendance->eq($jan01));
        $this->assertEquals(10, $thrall->totalReports);
        $this->assertEquals(8, $thrall->reportsAttended);
        $this->assertEquals(80.0, $thrall->percentage);
    }

    public function test_aggregate_returns_multiple_players_from_single_set(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $stats = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
            $this->makeStats('Jaina', $jan01, 10, 9),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$stats]));

        $this->assertCount(2, $result);
    }

    // ==================== Multiple Sets Tests ====================

    public function test_aggregate_sums_reports_across_sets_for_same_player(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $set1 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);
        $set2 = collect([
            $this->makeStats('Thrall', $jan01, 5, 5),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $this->assertCount(1, $result);

        $thrall = $result->first();
        $this->assertEquals(15, $thrall->totalReports);
        $this->assertEquals(13, $thrall->reportsAttended);
        $this->assertEquals(86.67, $thrall->percentage);
    }

    public function test_aggregate_keeps_earliest_first_attendance(): void
    {
        $jan01 = Carbon::parse('2025-01-01');
        $jan15 = Carbon::parse('2025-01-15');

        $set1 = collect([
            $this->makeStats('Thrall', $jan15, 5, 5),
        ]);
        $set2 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $thrall = $result->first();
        $this->assertTrue($thrall->firstAttendance->eq($jan01));
    }

    public function test_aggregate_keeps_earliest_first_attendance_regardless_of_set_order(): void
    {
        $jan01 = Carbon::parse('2025-01-01');
        $jan15 = Carbon::parse('2025-01-15');

        // Earliest date is in first set
        $set1 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);
        $set2 = collect([
            $this->makeStats('Thrall', $jan15, 5, 5),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $thrall = $result->first();
        $this->assertTrue($thrall->firstAttendance->eq($jan01));
    }

    public function test_aggregate_combines_different_players_from_multiple_sets(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $set1 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);
        $set2 = collect([
            $this->makeStats('Jaina', $jan01, 5, 5),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $this->assertCount(2, $result);
        $this->assertNotNull($result->firstWhere('name', 'Thrall'));
        $this->assertNotNull($result->firstWhere('name', 'Jaina'));
    }

    public function test_aggregate_handles_players_in_some_sets_but_not_others(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $set1 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
            $this->makeStats('Jaina', $jan01, 10, 10),
        ]);
        $set2 = collect([
            $this->makeStats('Thrall', $jan01, 5, 5),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $this->assertCount(2, $result);

        $thrall = $result->firstWhere('name', 'Thrall');
        $this->assertEquals(15, $thrall->totalReports);
        $this->assertEquals(13, $thrall->reportsAttended);

        $jaina = $result->firstWhere('name', 'Jaina');
        $this->assertEquals(10, $jaina->totalReports);
        $this->assertEquals(10, $jaina->reportsAttended);
    }

    // ==================== Sorting Tests ====================

    public function test_aggregate_results_are_sorted_alphabetically_by_name(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $stats = collect([
            $this->makeStats('Zara', $jan01, 10, 10),
            $this->makeStats('Alice', $jan01, 10, 10),
            $this->makeStats('Milo', $jan01, 10, 10),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$stats]));

        $this->assertEquals('Alice', $result[0]->name);
        $this->assertEquals('Milo', $result[1]->name);
        $this->assertEquals('Zara', $result[2]->name);
    }

    // ==================== Percentage Calculation Tests ====================

    public function test_aggregate_recalculates_percentage_from_summed_values(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        // Set 1: 80% (8/10)
        $set1 = collect([
            $this->makeStats('Thrall', $jan01, 10, 8),
        ]);
        // Set 2: 100% (5/5)
        $set2 = collect([
            $this->makeStats('Thrall', $jan01, 5, 5),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2]));

        $thrall = $result->first();
        // Combined: 13/15 = 86.67%
        $this->assertEquals(86.67, $thrall->percentage);
    }

    public function test_aggregate_handles_zero_total_reports(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $stats = collect([
            new PlayerAttendanceStats(
                name: 'Thrall',
                firstAttendance: $jan01,
                totalReports: 0,
                reportsAttended: 0,
                percentage: 0.0,
            ),
        ]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$stats]));

        $thrall = $result->first();
        $this->assertEquals(0.0, $thrall->percentage);
    }

    // ==================== Edge Cases ====================

    public function test_aggregate_handles_three_or_more_sets(): void
    {
        $jan01 = Carbon::parse('2025-01-01');

        $set1 = collect([$this->makeStats('Thrall', $jan01, 10, 8)]);
        $set2 = collect([$this->makeStats('Thrall', $jan01, 10, 9)]);
        $set3 = collect([$this->makeStats('Thrall', $jan01, 10, 10)]);

        $aggregator = new ReportsAggregator;
        $result = $aggregator->aggregate(collect([$set1, $set2, $set3]));

        $thrall = $result->first();
        $this->assertEquals(30, $thrall->totalReports);
        $this->assertEquals(27, $thrall->reportsAttended);
        $this->assertEquals(90.0, $thrall->percentage);
    }
}
