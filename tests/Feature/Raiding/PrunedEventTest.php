<?php

namespace Tests\Feature\Raiding;

use App\Events\ModelPruned;
use App\Models\Event;
use App\Models\PrunedModel;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrunedEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_a_model_pruned_event_when_an_event_is_pruned(): void
    {
        EventFacade::fake();

        $event = Event::factory()->create([
            'end_time' => now()->subMonths(2),
        ]);

        $event->prune();

        EventFacade::assertDispatched(ModelPruned::class, function (ModelPruned $dispatched) use ($event) {
            return $dispatched->model->is($event);
        });
    }

    #[Test]
    public function it_records_a_tombstone_when_an_event_is_pruned(): void
    {
        $event = Event::factory()->create([
            'end_time' => now()->subMonths(2),
        ]);

        $eventId = $event->id;

        $event->prune();

        $this->assertDatabaseHas('pruned_models', [
            'id' => $eventId,
            'type' => Event::class,
        ]);
        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    #[Test]
    public function it_returns_410_for_a_pruned_event(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')->andReturn(
                new Channel(id: '123', name: 'test-channel')
            );
        });

        $eventId = fake()->uuid();

        PrunedModel::create(['id' => $eventId, 'type' => Event::class]);

        $response = $this->get(route('raiding.plans.show', $eventId));

        $response->assertStatus(410);
    }

    #[Test]
    public function it_returns_404_for_an_event_uuid_that_was_never_pruned(): void
    {
        $response = $this->get(route('raiding.plans.show', fake()->uuid()));

        $response->assertStatus(404);
    }

    #[Test]
    public function it_renders_the_gone_inertia_page_for_a_pruned_event(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')->andReturn(
                new Channel(id: '123', name: 'test-channel')
            );
        });

        $eventId = fake()->uuid();

        PrunedModel::create(['id' => $eventId, 'type' => Event::class]);

        $this->get(route('raiding.plans.show', $eventId))
            ->assertStatus(410)
            ->assertInertia(fn ($page) => $page->component('Errors/Gone'));
    }

    #[Test]
    public function it_returns_json_410_for_a_pruned_event_on_api_requests(): void
    {
        $user = User::factory()->create();

        $eventId = fake()->uuid();

        PrunedModel::create(['id' => $eventId, 'type' => Event::class]);

        $this->actingAs($user)->patchJson("/api/events/{$eventId}/groups/reorder", [])
            ->assertStatus(410)
            ->assertJson(['message' => 'The requested resource has been pruned and is no longer available.']);
    }
}
