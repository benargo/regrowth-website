<?php

namespace Tests\Feature\Api\Event;

use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PublishAssignmentsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $editor;

    protected User $viewer;

    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $memberRole = DiscordRole::create([
            'id' => '829022020301094922',
            'name' => 'Member',
            'position' => 1,
            'is_visible' => true,
        ]);
        $memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));

        $this->editor = User::factory()->create();
        $this->editor->discordRoles()->attach($memberRole->id);

        $this->viewer = User::factory()->create();

        $this->event = Event::factory()->create(['channel_id' => '123456789012345678']);
    }

    #[Test]
    public function it_dispatches_the_notification_and_returns_204(): void
    {
        Queue::fake();

        $this->mock(Discord::class)
            ->shouldReceive('getChannel')
            ->once()
            ->with($this->event->channel_id)
            ->andReturn(Channel::from(['id' => $this->event->channel_id]));

        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $this->event));

        $response->assertNoContent();

        Queue::assertPushed(SendQueuedNotifications::class, function ($job) {
            return $job->notification instanceof RaidAssignmentsPublished;
        });
    }

    #[Test]
    public function it_targets_an_existing_notification_for_the_same_event(): void
    {
        Queue::fake();

        $this->mock(Discord::class)
            ->shouldReceive('getChannel')
            ->once()
            ->with($this->event->channel_id)
            ->andReturn(Channel::from(['id' => $this->event->channel_id]));

        $existing = DiscordNotification::factory()
            ->withRelatedModels([Event::class => [$this->event->getKey()]])
            ->create(['type' => RaidAssignmentsPublished::class]);

        $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $this->event))
            ->assertNoContent();

        $pushed = Queue::pushed(SendQueuedNotifications::class)->first();
        $this->assertNotNull($pushed, 'No SendQueuedNotifications job was pushed');
        $this->assertInstanceOf(RaidAssignmentsPublished::class, $pushed->notification);
        $this->assertSame($existing->id, $pushed->notification->updates()?->id);
    }

    #[Test]
    public function it_does_not_target_a_notification_for_a_different_event(): void
    {
        Queue::fake();

        $this->mock(Discord::class)
            ->shouldReceive('getChannel')
            ->once()
            ->with($this->event->channel_id)
            ->andReturn(Channel::from(['id' => $this->event->channel_id]));

        $otherEvent = Event::factory()->create();
        DiscordNotification::factory()
            ->withRelatedModels([Event::class => [$otherEvent->getKey()]])
            ->create(['type' => RaidAssignmentsPublished::class]);

        $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $this->event));

        Queue::assertPushed(SendQueuedNotifications::class, function ($job) {
            return $job->notification instanceof RaidAssignmentsPublished
                && $job->notification->updates() === null;
        });
    }

    #[Test]
    public function it_returns_403_when_user_cannot_update_event(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson(route('api.events.publish-assignments', $this->event));

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_401_when_unauthenticated(): void
    {
        $this->postJson(route('api.events.publish-assignments', $this->event))
            ->assertUnauthorized();
    }
}
