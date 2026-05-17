<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Boss;
use App\Models\Event;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // phpunit.xml uses BROADCAST_CONNECTION=log to prevent broadcast pushes during tests.
        // Channel auth signing requires the Reverb (Pusher-compatible) broadcaster.
        // We swap drivers here and forget the bound singleton so the new config takes effect.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => config('broadcasting.connections.reverb.key', 'test-app-key'),
            'broadcasting.connections.reverb.secret' => config('broadcasting.connections.reverb.secret', 'test-app-secret'),
            'broadcasting.connections.reverb.app_id' => config('broadcasting.connections.reverb.app_id', 'test-app-id'),
        ]);
        app()->forgetInstance(BroadcastManager::class);
        app()->forgetInstance(Broadcaster::class);

        require base_path('routes/channels.php');
    }

    private function authChannel(User $user, string $channel): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ]);
    }

    // ─── Private event channel ────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_join_private_event_channel(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->authChannel($user, "private-event.{$event->id}")->assertOk();
    }

    #[Test]
    public function unauthenticated_user_cannot_join_private_event_channel(): void
    {
        $event = Event::factory()->create();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-event.{$event->id}",
        ])->assertUnauthorized();
    }

    // ─── Presence editors channel ─────────────────────────────────────────────

    #[Test]
    public function user_with_manage_raid_plans_can_join_presence_editors_channel(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();

        $response = $this->authChannel($user, "presence-event.{$event->id}.editors");

        $response->assertOk();
        $response->assertJsonStructure(['auth', 'channel_data']);
    }

    #[Test]
    public function user_without_manage_raid_plans_cannot_join_presence_editors_channel(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->authChannel($user, "presence-event.{$event->id}.editors")->assertForbidden();
    }

    #[Test]
    public function unauthenticated_user_cannot_join_presence_editors_channel(): void
    {
        $event = Event::factory()->create();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "presence-event.{$event->id}.editors",
        ])->assertUnauthorized();
    }

    #[Test]
    public function presence_channel_data_includes_user_id_name_and_avatar_url(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();

        $response = $this->authChannel($user, "presence-event.{$event->id}.editors");

        // channel_data is a JSON string containing user_info as a nested JSON string
        $channelData = json_decode($response->json('channel_data'), true);
        $userInfo = is_string($channelData['user_info'])
            ? json_decode($channelData['user_info'], true)
            : $channelData['user_info'];

        $this->assertArrayHasKey('id', $userInfo);
        $this->assertArrayHasKey('name', $userInfo);
        $this->assertArrayHasKey('avatar_url', $userInfo);
        $this->assertEquals($user->id, $userInfo['id']);
    }

    // ─── Boss strategy channel ─────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_join_private_boss_channel(): void
    {
        $user = User::factory()->create();
        $boss = Boss::factory()->create();

        $this->authChannel($user, "private-boss.{$boss->id}")->assertOk();
    }

    #[Test]
    public function unauthenticated_user_cannot_join_private_boss_channel(): void
    {
        $boss = Boss::factory()->create();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-boss.{$boss->id}",
        ])->assertUnauthorized();
    }
}
