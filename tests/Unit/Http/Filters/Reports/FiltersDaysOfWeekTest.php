<?php

namespace Tests\Unit\Http\Filters\Reports;

use App\Http\Filters\Reports\FiltersDaysOfWeek;
use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiltersDaysOfWeekTest extends TestCase
{
    use RefreshDatabase;

    private function reportOnDay(int $carbonDayOfWeek): Report
    {
        // Carbon: 0=Sunday, 1=Monday, ..., 6=Saturday
        $date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($carbonDayOfWeek);

        return Report::factory()->create([
            'start_time' => $date->copy()->setTime(20, 0),
            'end_time' => $date->copy()->setTime(23, 0),
        ]);
    }

    private function applyFilter(mixed $value): Collection
    {
        $query = Report::query();
        (new FiltersDaysOfWeek)($query, $value, 'days');

        return $query->get();
    }

    // ==================== single day ====================

    #[Test]
    public function it_filters_to_a_single_day(): void
    {
        $wednesday = $this->reportOnDay(3);
        $friday = $this->reportOnDay(5);

        $results = $this->applyFilter('3');

        $this->assertTrue($results->contains($wednesday));
        $this->assertFalse($results->contains($friday));
    }

    // ==================== multiple days ====================

    #[Test]
    public function it_filters_to_multiple_days_with_or_logic(): void
    {
        $wednesday = $this->reportOnDay(3);
        $thursday = $this->reportOnDay(4);
        $friday = $this->reportOnDay(5);

        $results = $this->applyFilter(['3', '4']);

        $this->assertTrue($results->contains($wednesday));
        $this->assertTrue($results->contains($thursday));
        $this->assertFalse($results->contains($friday));
    }

    // ==================== string vs array ====================

    #[Test]
    public function it_handles_a_single_value_passed_as_a_string(): void
    {
        $monday = $this->reportOnDay(1);
        $sunday = $this->reportOnDay(0);

        $results = $this->applyFilter('1');

        $this->assertTrue($results->contains($monday));
        $this->assertFalse($results->contains($sunday));
    }
}
