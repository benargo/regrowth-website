<?php

namespace Tests\Unit\Models\TBC;

use App\Models\TBC\Phase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PhaseTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Phase::class;
    }

    #[Test]
    public function it_uses_tbc_phases_table(): void
    {
        $model = new Phase;

        $this->assertSame('tbc_phases', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Phase;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Phase;

        $this->assertFillable($model, [
            'description',
            'start_date',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Phase;

        $this->assertCasts($model, [
            'start_date' => 'datetime',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $phase = $this->create([
            'description' => 'Phase 1: Karazhan',
        ]);

        $this->assertTableHas(['description' => 'Phase 1: Karazhan']);
        $this->assertModelExists($phase);
    }

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $startDate = now()->subMonth()->startOfSecond();

        $phase = $this->create([
            'description' => 'Phase 2: Gruul & Magtheridon',
            'start_date' => $startDate,
        ]);

        $this->assertTableHas([
            'description' => 'Phase 2: Gruul & Magtheridon',
        ]);
        $this->assertModelExists($phase);
        $this->assertSame($startDate->format('Y-m-d H:i:s'), $phase->start_date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_casts_start_date_to_datetime(): void
    {
        $phase = $this->create([
            'description' => 'Test Phase',
            'start_date' => '2024-06-15 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $phase->start_date);
        $this->assertSame('2024-06-15 10:00:00', $phase->start_date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_allows_null_start_date(): void
    {
        $phase = $this->create([
            'description' => 'Unscheduled Phase',
            'start_date' => null,
        ]);

        $this->assertNull($phase->start_date);
        $this->assertModelExists($phase);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $phase = $this->create();

        $this->assertNotEmpty($phase->description);
        $this->assertModelExists($phase);
    }

    #[Test]
    public function factory_started_state_sets_past_start_date(): void
    {
        $phase = $this->factory()->started()->create();

        $this->assertNotNull($phase->start_date);
        $this->assertTrue($phase->start_date->isPast());
    }

    #[Test]
    public function factory_upcoming_state_sets_future_start_date(): void
    {
        $phase = $this->factory()->upcoming()->create();

        $this->assertNotNull($phase->start_date);
        $this->assertTrue($phase->start_date->isFuture());
    }

    #[Test]
    public function factory_unscheduled_state_sets_null_start_date(): void
    {
        $phase = $this->factory()->unscheduled()->create();

        $this->assertNull($phase->start_date);
    }
}
