<?php

namespace Tests\Unit\Services\Discord\Notifications;

use App\Contracts\Notifications\DiscordMessage;
use App\Models\DiscordNotification;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Notifications\Driver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DriverTest extends TestCase
{
    use RefreshDatabase;

    private Discord&MockInterface $discord;

    private Driver $driver;

    private Channel $channel;

    private NotifiableChannel $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = Mockery::mock(Discord::class);
        $this->driver = new Driver($this->discord);

        $this->channel = Channel::from(['id' => '987654321098765432', 'type' => ChannelType::GUILD_TEXT->value]);
        $this->notifiable = new NotifiableChannel($this->channel);
    }

    private function makeDiscordMessage(string $id): Message
    {
        return Message::from([
            'id' => $id,
            'channel_id' => $this->channel->id,
            'timestamp' => '2024-01-01T00:00:00.000000+00:00',
            'tts' => false,
            'mention_everyone' => false,
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // send — no existing message (create path)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_creates_a_new_discord_message_when_no_updates_notification_exists(): void
    {
        $payload = MessagePayload::from(['content' => 'Hello!']);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->expects('updates')->once()->andReturnNull();
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('sender')->never();

        $this->discord->expects('createMessage')
            ->with($this->channel, $payload)
            ->andReturn($this->makeDiscordMessage('111111111111111111'));

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '111111111111111111',
            'channel_id' => $this->channel->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // send — existing message found (edit path)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_edits_the_existing_discord_message_when_a_message_id_is_known(): void
    {
        $payload = MessagePayload::from(['content' => 'Updated!']);

        $existingNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '222222222222222222',
        ]);

        $existingDiscordMessage = $this->makeDiscordMessage('222222222222222222');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->expects('updates')->once()->andReturn($existingNotification);
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '222222222222222222')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($this->channel, $existingDiscordMessage, $payload)
            ->andReturn($existingDiscordMessage);

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'id' => $existingNotification->id,
            'message_id' => '222222222222222222',
        ]);
    }

    #[Test]
    public function it_records_the_sender_when_editing_an_existing_message(): void
    {
        $user = User::factory()->create();
        $payload = MessagePayload::from(['content' => 'Edited by user']);

        $existingNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '333333333333333333',
        ]);

        $existingDiscordMessage = $this->makeDiscordMessage('333333333333333333');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->expects('updates')->once()->andReturn($existingNotification);
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturn($user);

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '333333333333333333')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($this->channel, $existingDiscordMessage, $payload)
            ->andReturn($existingDiscordMessage);

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'id' => $existingNotification->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_call_create_message_when_edit_succeeds(): void
    {
        $payload = MessagePayload::from(['content' => 'No create needed']);

        $existingNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '666666666666666666',
        ]);

        $existingDiscordMessage = $this->makeDiscordMessage('666666666666666666');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->expects('updates')->once()->andReturn($existingNotification);
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();

        $this->discord->expects('getChannelMessage')->andReturn($existingDiscordMessage);
        $this->discord->expects('editMessage')->andReturn($existingDiscordMessage);
        $this->discord->expects('createMessage')->never();

        $this->driver->send($this->notifiable, $notification);
    }

    // -------------------------------------------------------------------------
    // send — existing message deleted (fallback create path)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_deletes_the_stale_db_record_and_creates_a_new_message_when_the_discord_message_no_longer_exists(): void
    {
        $payload = MessagePayload::from(['content' => 'Recovered!']);

        $staleNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '444444444444444444',
        ]);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->expects('updates')->once()->andReturn($staleNotification);
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('sender')->never();

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '444444444444444444')
            ->andThrow(new RuntimeException('Message not found'));

        $this->discord->expects('createMessage')
            ->with($this->channel, $payload)
            ->andReturn($this->makeDiscordMessage('555555555555555555'));

        $this->driver->send($this->notifiable, $notification);

        $this->assertSoftDeleted('discord_notifications', ['id' => $staleNotification->id]);
        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '555555555555555555',
            'channel_id' => $this->channel->id,
        ]);
    }
}
