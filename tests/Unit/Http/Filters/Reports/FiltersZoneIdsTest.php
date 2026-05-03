<?php

namespace Tests\Unit\Http\Filters\Reports;

use App\Http\Filters\Reports\FiltersZoneIds;
use App\Models\Raids\Report;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiltersZoneIdsTest extends TestCase
{
    use RefreshDatabase;

    private function applyFilter(mixed $value): Collection
    {
        $query = Report::query();
        (new FiltersZoneIds)($query, $value, 'zone_ids');

        return $query->get();
    }

    // ==================== single zone ID ====================

    #[Test]
    public function it_filters_to_a_single_zone(): void
    {
        $zone = Zone::factory()->create();
        $match = Report::factory()->withZone($zone)->create();
        $other = Report::factory()->withZone(Zone::factory()->create())->create();

        $results = $this->applyFilter((string) $zone->id);

        $this->assertTrue($results->contains($match));
        $this->assertFalse($results->contains($other));
    }

    // ==================== multiple zone IDs ====================

    #[Test]
    public function it_filters_to_multiple_zones_with_or_logic(): void
    {
        $zone1 = Zone::factory()->create();
        $zone2 = Zone::factory()->create();
        $zone3 = Zone::factory()->create();

        $match1 = Report::factory()->withZone($zone1)->create();
        $match2 = Report::factory()->withZone($zone2)->create();
        $excluded = Report::factory()->withZone($zone3)->create();

        $results = $this->applyFilter([(string) $zone1->id, (string) $zone2->id]);

        $this->assertTrue($results->contains($match1));
        $this->assertTrue($results->contains($match2));
        $this->assertFalse($results->contains($excluded));
    }

    // ==================== zone ID 0 → IS NULL ====================

    #[Test]
    public function it_filters_for_reports_with_no_zone_when_id_is_zero(): void
    {
        $unzoned = Report::factory()->withoutZone()->create();
        $zoned = Report::factory()->withZone()->create();

        $results = $this->applyFilter('0');

        $this->assertTrue($results->contains($unzoned));
        $this->assertFalse($results->contains($zoned));
    }

    // ==================== mixed 0 + real IDs ====================

    #[Test]
    public function it_combines_null_zone_and_real_zone_ids_with_or_logic(): void
    {
        $zone = Zone::factory()->create();
        $unzoned = Report::factory()->withoutZone()->create();
        $match = Report::factory()->withZone($zone)->create();
        $other = Report::factory()->withZone(Zone::factory()->create())->create();

        $results = $this->applyFilter(['0', (string) $zone->id]);

        $this->assertTrue($results->contains($unzoned));
        $this->assertTrue($results->contains($match));
        $this->assertFalse($results->contains($other));
    }

    // ==================== string vs array ====================

    #[Test]
    public function it_handles_a_single_value_passed_as_a_string(): void
    {
        $zone = Zone::factory()->create();
        $match = Report::factory()->withZone($zone)->create();
        $other = Report::factory()->withZone(Zone::factory()->create())->create();

        $results = $this->applyFilter((string) $zone->id);

        $this->assertTrue($results->contains($match));
        $this->assertFalse($results->contains($other));
    }
}
