<?php

namespace Tests\Unit\Notifications;

use App\Enums\RaidColor;
use App\Models\Event;
use App\Models\Raid;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaidAssignmentsPublishedTest extends TestCase
{
    use RefreshDatabase;

    private NotifiableChannel $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::from(['id' => '123456789012345678', 'type' => 0]);
        $this->notifiable = new NotifiableChannel($channel);
    }

    // -------------------------------------------------------------------------
    // via()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_routes_through_the_discord_driver(): void
    {
        $event = Event::factory()->create();

        $notification = new RaidAssignmentsPublished($event);

        $this->assertContains(DiscordDriver::class, $notification->via($this->notifiable));
    }

    // -------------------------------------------------------------------------
    // ShouldBroadcast / broadcasting
    // -------------------------------------------------------------------------

    #[Test]
    public function it_implements_should_broadcast(): void
    {
        $event = Event::factory()->create();

        $this->assertInstanceOf(ShouldBroadcast::class, new RaidAssignmentsPublished($event));
    }

    #[Test]
    public function it_includes_broadcast_channel_in_via_when_sender_is_set(): void
    {
        $event = Event::factory()->create();
        $sender = User::factory()->create();
        $notification = (new RaidAssignmentsPublished($event))->withSender($sender);

        $this->assertContains('broadcast', $notification->via($this->notifiable));
    }

    #[Test]
    public function it_does_not_include_broadcast_channel_in_via_without_sender(): void
    {
        $event = Event::factory()->create();

        $this->assertNotContains('broadcast', (new RaidAssignmentsPublished($event))->via($this->notifiable));
    }

    #[Test]
    public function it_broadcasts_on_the_senders_private_user_channel(): void
    {
        $event = Event::factory()->create();
        $sender = User::factory()->create();
        $notification = (new RaidAssignmentsPublished($event))->withSender($sender);

        $channels = $notification->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-App.Models.User.{$sender->id}", $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_a_success_message_payload(): void
    {
        $event = Event::factory()->create();
        $sender = User::factory()->create();
        $notification = (new RaidAssignmentsPublished($event))->withSender($sender);

        $payload = $notification->broadcastWith();

        $this->assertArrayHasKey('message', $payload);
        $this->assertSame('Assignments published to Discord successfully.', $payload['message']);
    }

    #[Test]
    public function it_broadcasts_as_assignments_published(): void
    {
        $event = Event::factory()->create();

        $this->assertSame('AssignmentsPublished', (new RaidAssignmentsPublished($event))->broadcastAs());
    }

    // -------------------------------------------------------------------------
    // toMessage() — embed structure
    // -------------------------------------------------------------------------

    #[Test]
    public function it_builds_an_embed_with_the_correct_title(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertSame('Assignments posted for tonight!', $embed->title);
    }

    #[Test]
    public function it_builds_an_embed_with_a_description(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertNotNull($embed->description);
    }

    #[Test]
    public function it_builds_an_embed_with_a_timestamp(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertNotNull($embed->timestamp);
    }

    #[Test]
    public function it_builds_an_embed_with_the_event_url(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertSame(route('raiding.plans.show', ['event' => $event->id]), $embed->url);
    }

    // -------------------------------------------------------------------------
    // toMessage() — color
    // -------------------------------------------------------------------------

    #[Test]
    public function it_uses_the_default_color_when_no_raids_are_attached(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertSame(RaidColor::DEFAULT->value, $embed->color);
    }

    #[Test]
    public function it_uses_the_raid_color_based_on_the_first_attached_raid(): void
    {
        $event = Event::factory()->create();
        $raid = Raid::factory()->create(['id' => 1]);
        $event->raids()->attach($raid->id);

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertSame(RaidColor::KARAZHAN->value, $embed->color);
    }

    // -------------------------------------------------------------------------
    // toMessage() — footer
    // -------------------------------------------------------------------------

    #[Test]
    public function it_omits_the_footer_when_no_sender_is_set(): void
    {
        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertNull($embed->footer);
    }

    #[Test]
    public function it_includes_the_sender_nickname_in_the_footer(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create(['nickname' => 'Illidan']);

        $embed = (new RaidAssignmentsPublished($event))->withSender($user)->toMessage()->embeds[0];

        $this->assertNotNull($embed->footer);
        $this->assertStringContainsString('Illidan', $embed->footer->text);
    }

    // -------------------------------------------------------------------------
    // toMessage() — image
    // -------------------------------------------------------------------------

    #[Test]
    public function it_includes_the_blueprint_image_from_storage_when_available(): void
    {
        Storage::fake();
        Storage::put('images/assignments_blueprint.webp', 'fake-image-content');

        $event = Event::factory()->create();

        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertNotNull($embed->image);
        $this->assertStringContainsString('assignments_blueprint', $embed->image->url);
    }

    #[Test]
    public function it_includes_the_blueprint_image_from_resources_as_fallback(): void
    {
        Storage::fake();

        $event = Event::factory()->create();

        // The blueprint exists at resource_path('images/assignments_blueprint.webp') in this repo,
        // so the resource fallback path is exercised and the image is included.
        $embed = (new RaidAssignmentsPublished($event))->toMessage()->embeds[0];

        $this->assertNotNull($embed->image);
        $this->assertStringContainsString('assignments_blueprint', $embed->image->url);
    }

    // -------------------------------------------------------------------------
    // toDatabase()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_correct_database_payload_structure(): void
    {
        $event = Event::factory()->create();

        $data = (new RaidAssignmentsPublished($event))->toDatabase($this->notifiable);

        $this->assertSame(RaidAssignmentsPublished::class, $data['type']);
        $this->assertSame($this->notifiable->channel()->id, $data['channel_id']);
        $this->assertArrayHasKey('payload', $data);
        $this->assertNull($data['created_by_user_id']);
    }

    #[Test]
    public function it_stores_the_sender_id_in_the_database_payload(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();

        $data = (new RaidAssignmentsPublished($event))->withSender($user)->toDatabase($this->notifiable);

        $this->assertSame($user->id, $data['created_by_user_id']);
    }

    // -------------------------------------------------------------------------
    // Serialization (for queue)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_can_be_serialized_for_the_queue(): void
    {
        $event = Event::factory()->create();
        $notification = new RaidAssignmentsPublished($event);

        $serialized = serialize($notification);

        $this->assertIsString($serialized);
        $this->assertNotEmpty($serialized);
    }

    #[Test]
    public function it_can_be_unserialized_from_the_queue(): void
    {
        $event = Event::factory()->create();
        $original = new RaidAssignmentsPublished($event);

        $serialized = serialize($original);
        /** @var RaidAssignmentsPublished $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(RaidAssignmentsPublished::class, $unserialized);
        $this->assertSame($event->getKey(), $unserialized->event->getKey());
    }

    #[Test]
    public function it_can_call_to_message_after_being_unserialized(): void
    {
        $event = Event::factory()->create();
        $original = new RaidAssignmentsPublished($event);

        $unserialized = unserialize(serialize($original));

        $this->assertInstanceOf(RaidAssignmentsPublished::class, $unserialized);
        $this->assertNotNull($unserialized->toMessage());
    }
}
