<?php

namespace Tests\Unit\Notifications;

use App\Enums\RaidColor;
use App\Models\DiscordNotification;
use App\Models\Event;
use App\Models\Raid;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->assertSame(DiscordDriver::class, $notification->via($this->notifiable));
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
        $this->assertArrayHasKey('related_models', $data);
        $this->assertNull($data['created_by_user_id']);
    }

    #[Test]
    public function it_stores_the_event_in_the_related_models(): void
    {
        $event = Event::factory()->create();

        $data = (new RaidAssignmentsPublished($event))->toDatabase($this->notifiable);

        $this->assertSame([Event::class => [$event->getKey()]], $data['related_models']);
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
    // updates() — UpdatesExisting
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_null_for_updates_when_no_existing_notification_matches(): void
    {
        $event = Event::factory()->create();

        $notification = new RaidAssignmentsPublished($event);

        $this->assertNull($notification->updates());
    }

    #[Test]
    public function it_targets_an_existing_notification_for_the_same_event(): void
    {
        $event = Event::factory()->create();
        $existing = DiscordNotification::factory()->create(['type' => RaidAssignmentsPublished::class]);
        DB::table('discord_notifications')
            ->where('id', $existing->id)
            ->update(['related_models' => json_encode([Event::class => [$event->getKey()]])]);

        $notification = new RaidAssignmentsPublished($event);

        $this->assertNotNull($notification->updates());
        $this->assertSame($existing->id, $notification->updates()->id);
    }

    #[Test]
    public function it_does_not_target_a_notification_for_a_different_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $other = DiscordNotification::factory()->create(['type' => RaidAssignmentsPublished::class]);
        DB::table('discord_notifications')
            ->where('id', $other->id)
            ->update(['related_models' => json_encode([Event::class => [$otherEvent->getKey()]])]);

        $notification = new RaidAssignmentsPublished($event);

        $this->assertNull($notification->updates());
    }
}
