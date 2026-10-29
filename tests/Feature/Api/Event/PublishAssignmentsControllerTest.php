<?php

namespace Tests\Feature\Api\Event;

use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Models\DiscordNotification;
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
        $stale = DiscordNotification::factory()
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
}
