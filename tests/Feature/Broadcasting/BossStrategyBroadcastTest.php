<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Boss;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('broadcasting')]
class BossStrategyBroadcastTest extends TestCase
{
    use RefreshDatabase;

    // ─── Boss model broadcasts ───────────────────────────────────────────────

    #[Test]
    public function boss_updated_broadcasts_on_private_boss_channel(): void
    {
        $boss = Boss::factory()->create();

        $channels = $boss->broadcastOn('updated');

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-boss.{$boss->id}", $channels[0]->name);
    }

    #[Test]
    public function boss_broadcasts_as_boss_strategy_changed_on_update(): void
    {
        $boss = Boss::factory()->create();

        $this->assertEquals('BossStrategyChanged', $boss->broadcastAs('updated'));
    }

    #[Test]
    public function boss_create_and_delete_do_not_broadcast(): void
    {
        $boss = Boss::factory()->create();

        $this->assertEquals([], $boss->broadcastOn('created'));
        $this->assertEquals([], $boss->broadcastOn('deleted'));
    }

    #[Test]
    public function boss_broadcast_with_includes_boss_resource_payload(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Stack on boss.']);

        $payload = $boss->broadcastWith('updated');

        $this->assertArrayHasKey('boss', $payload);
        $this->assertEquals($boss->id, $payload['boss']['id']);
        $this->assertEquals('Stack on boss.', $payload['boss']['notes']);
    }

    #[Test]
    public function boss_strategy_update_queues_model_broadcast(): void
    {
        $user = User::factory()->withPermissions('manage-boss-strategies', 'view-officer-dashboard')->create();
        $boss = Boss::factory()->create();
        $user->refresh();

        Queue::fake();

        $this->actingAs($user)
            ->patch(route('management.boss-strategies.update', $boss), [
                'notes' => 'Updated notes.',
            ])
            ->assertRedirect();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model instanceof Boss
                && $job->event->event() === 'updated',
        );
    }

    // ─── Cache invalidation ──────────────────────────────────────────────────

    #[Test]
    public function boss_save_flushes_raiding_cache(): void
    {
        Cache::tags(['raiding', 'events'])->put('canary', 'value', 60);

        Boss::factory()->create();

        $this->assertNull(Cache::tags(['raiding', 'events'])->get('canary'));
    }
}
