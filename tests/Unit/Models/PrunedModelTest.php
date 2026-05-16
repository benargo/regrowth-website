<?php

namespace Tests\Unit\Models;

use App\Models\PrunedModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrunedModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_no_auto_increment_timestamps(): void
    {
        $model = new PrunedModel;

        $this->assertFalse($model->timestamps);
    }

    #[Test]
    public function it_can_be_created_with_id_type_and_pruned_at(): void
    {
        $uuid = fake()->uuid();

        PrunedModel::create([
            'id' => $uuid,
            'type' => 'App\\Models\\Event',
        ]);

        $this->assertDatabaseHas('pruned_models', [
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
