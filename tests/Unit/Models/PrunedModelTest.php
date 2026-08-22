<?php

namespace Tests\Unit\Models;

use App\Models\PrunedModel;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('platform')]
class PrunedModelTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PrunedModel::class;
    }

    #[Test]
    public function it_has_no_auto_increment_timestamps(): void
    {
        $model = new PrunedModel;

        $this->assertFalse($model->timestamps);
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PrunedModel;

        $this->assertFillable($model, ['id', 'type', 'pruned_at']);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new PrunedModel;

        $this->assertFillableAttribute($model, ['id', 'type', 'pruned_at']);
    }

    #[Test]
    public function it_can_be_created_with_id_type_and_pruned_at(): void
    {
        $uuid = fake()->uuid();

        PrunedModel::create([
            'id' => $uuid,
            'type' => 'App\\Models\\Event',
        ]);

        $this->assertTableHas([
            'id' => $uuid,
            'type' => 'App\\Models\\Event',
        ]);
    }

    #[Test]
    public function it_casts_pruned_at_as_datetime(): void
    {
        $uuid = fake()->uuid();

        PrunedModel::create([
            'id' => $uuid,
            'type' => 'App\\Models\\Event',
        ]);

        $pruned = PrunedModel::where('id', $uuid)->firstOrFail();

        $this->assertInstanceOf(Carbon::class, $pruned->pruned_at);
    }
}
