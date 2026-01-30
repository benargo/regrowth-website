<?php

namespace Tests\Unit\Http\Resources\TBC;

use App\Http\Resources\TBC\PhaseResource;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhaseResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_id(): void
    {
        $phase = Phase::factory()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertSame($phase->id, $array['id']);
    }

    #[Test]
    public function it_returns_description(): void
    {
        $phase = Phase::factory()->create(['description' => 'Phase 1: Karazhan']);

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertSame('Phase 1: Karazhan', $array['description']);
    }

    #[Test]
    public function it_returns_start_date_in_iso8601_format_when_present(): void
    {
        $startDate = now()->startOfSecond();
        $phase = Phase::factory()->create(['start_date' => $startDate]);

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertSame($startDate->toIso8601String(), $array['start_date']);
    }

    #[Test]
    public function it_returns_null_for_start_date_when_not_set(): void
    {
        $phase = Phase::factory()->unscheduled()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['start_date']);
    }

    #[Test]
    public function it_returns_has_started_true_when_phase_has_started(): void
    {
        $phase = Phase::factory()->started()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertTrue($array['has_started']);
    }

    #[Test]
    public function it_returns_has_started_false_when_phase_has_not_started(): void
    {
        $phase = Phase::factory()->upcoming()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['has_started']);
    }

    #[Test]
    public function it_returns_has_started_false_when_start_date_is_null(): void
    {
        $phase = Phase::factory()->unscheduled()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['has_started']);
    }

    #[Test]
    public function it_includes_raids_when_loaded(): void
    {
        $phase = Phase::factory()->create();
        Raid::factory()->count(2)->create(['phase_id' => $phase->id]);
        $phase->load('raids');

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('raids', $array);
        $this->assertCount(2, $array['raids']);
    }

    #[Test]
    public function it_excludes_raids_when_not_loaded(): void
    {
        $phase = Phase::factory()->create();
        Raid::factory()->count(2)->create(['phase_id' => $phase->id]);

        $resource = new PhaseResource($phase);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('raids', $array);
    }

    #[Test]
    public function it_includes_bosses_when_loaded(): void
    {
        $phase = Phase::factory()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);
        $phase->load('bosses');

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('bosses', $array);
        $this->assertCount(3, $array['bosses']);
    }

    #[Test]
    public function it_excludes_bosses_when_not_loaded(): void
    {
        $phase = Phase::factory()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        $resource = new PhaseResource($phase);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('bosses', $array);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $phase = Phase::factory()->started()->create();

        $resource = new PhaseResource($phase);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('has_started', $array);
    }
}
