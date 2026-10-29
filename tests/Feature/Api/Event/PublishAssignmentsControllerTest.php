<?php

namespace Tests\Feature\Api\Event;

use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Models\DiscordChannel;
use App\Models\DiscordMessage;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class PublishAssignmentsControllerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected User $editor;

    protected User $viewer;

    protected DiscordChannel $channel;

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

        $this->channel = DiscordChannel::factory()->create(['id' => '123456789012345678']);
        $this->event = Event::factory()->for($this->channel, 'channel')->create();
    }

    #[Test]
    public function it_dispatches_the_notification_and_returns_204(): void
    {
        Queue::fake();

        $this->fakeDiscord([]);

        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $this->event));

        $response->assertNoContent();

        Queue::assertPushed(SendQueuedNotifications::class, function ($job) {
            return $job->notification instanceof RaidAssignmentsPublished;
        });

        Saloon::assertNothingSent();
    }

    #[Test]
    public function republishing_after_the_discord_message_was_deleted_posts_nothing(): void
    {
        Storage::fake('s3');
        $stale = DiscordMessage::factory()
            ->withRelatedModels([['model_type' => Event::class, 'model_id' => $this->event->getKey()]])
            ->create([
                'type' => RaidAssignmentsPublished::class,
                'channel_id' => $this->event->channel_id,
                'message_id' => '343434343434343434',
            ]);

        $this->fakeDiscord([
            EditMessageRequest::class => $this->makeDiscordErrorResponse(404, 10008, 'Unknown Message'),
            CreateMessageRequest::class => MockResponse::make($this->makeDiscordMessageResponse('565656565656565656', $this->event->channel_id)),
        ]);

        $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $this->event))
            ->assertNoContent();

        $this->assertSoftDeleted($stale);
        Saloon::assertNotSent(CreateMessageRequest::class);
    }

    #[Test]
    public function it_does_not_publish_to_a_soft_deleted_channel(): void
    {
        Storage::fake('s3');
        $channel = DiscordChannel::factory()->trashed()->create();
        $event = Event::factory()->for($channel, 'channel')->create();
        $this->fakeDiscordMessageCreated('787878787878787878', $channel->id);

        $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $event))
            ->assertNoContent();

        Saloon::assertNothingSent();
        $this->assertDatabaseMissing('discord_messages', ['channel_id' => $channel->id]);
    }

    #[Test]
    public function it_publishes_to_an_unsynced_stub_channel(): void
    {
        $channel = DiscordChannel::factory()->stub()->create();

        $this->assertPublishesTo($channel);
    }

    #[Group('authorization')]
    #[Test]
    public function it_returns_403_when_user_cannot_update_event(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson(route('api.events.publish-assignments', $this->event));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function it_returns_401_when_unauthenticated(): void
    {
        $this->postJson(route('api.events.publish-assignments', $this->event))
            ->assertUnauthorized();
    }

    // ==================== helpers ====================

    private function assertPublishesTo(DiscordChannel $channel): void
    {
        Storage::fake('s3');
        $event = Event::factory()->for($channel, 'channel')->create();
        $this->fakeDiscordMessageCreated('787878787878787878', $channel->id);

        $this->actingAs($this->editor)
            ->postJson(route('api.events.publish-assignments', $event))
            ->assertNoContent();

        Saloon::assertSent(fn (Request $request): bool => $request instanceof CreateMessageRequest
            && $request->resolveEndpoint() === "/channels/{$channel->id}/messages");
        $this->assertDatabaseHas('discord_messages', [
            'channel_id' => $channel->id,
            'message_id' => '787878787878787878',
        ]);
    }
}
