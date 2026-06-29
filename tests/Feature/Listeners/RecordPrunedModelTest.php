<?php

namespace Tests\Feature\Listeners;

use App\Events\ModelPruned;
use App\Models\Event;
use App\Models\PrunedModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RecordPrunedModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_a_pruned_model_in_the_tombstone_table(): void
    {
        $event = Event::factory()->create();
        $eventId = $event->id;

        ModelPruned::dispatch($event);

        $this->assertDatabaseHas('pruned_models', [
            'id' => $eventId,
            'type' => Event::class,
        ]);
    }

    #[Test]
    public function it_sets_pruned_at_timestamp_to_current_time(): void
    {
        $event = Event::factory()->create();

        ModelPruned::dispatch($event);

        $pruned = PrunedModel::where('id', $event->id)->firstOrFail();

        $this->assertNotNull($pruned->pruned_at);
        $this->assertLessThanOrEqual(5, now()->diffInSeconds($pruned->pruned_at));
    }

    #[Test]
    public function it_records_the_fully_qualified_class_name(): void
    {
        $event = Event::factory()->create();

        ModelPruned::dispatch($event);

        $pruned = PrunedModel::where('id', $event->id)->firstOrFail();

        $this->assertEquals(Event::class, $pruned->type);
        $this->assertStringContainsString('App\\Models\\Event', $pruned->type);
    }

    #[Test]
    public function it_is_idempotent_when_dispatched_twice_for_the_same_model(): void
    {
        $event = Event::factory()->create();

        ModelPruned::dispatch($event);
        ModelPruned::dispatch($event);

        $this->assertDatabaseCount('pruned_models', 1);
    }

    #[Test]
    public function it_handles_multiple_pruned_models_with_same_type(): void
    {
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        ModelPruned::dispatch($event1);
        ModelPruned::dispatch($event2);

        $this->assertDatabaseHas('pruned_models', [
            'id' => $event1->id,
            'type' => Event::class,
        ]);
        $this->assertDatabaseHas('pruned_models', [
            'id' => $event2->id,
            'type' => Event::class,
        ]);
        $this->assertCount(2, PrunedModel::all());
    }
}
