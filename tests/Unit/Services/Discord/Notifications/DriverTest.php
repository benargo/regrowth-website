<?php

namespace Tests\Unit\Services\Discord\Notifications;

use App\Models\DiscordNotification;
use App\Models\User;
use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;
use App\Services\Discord\Discord;
use App\Services\Discord\Enums\ChannelType;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Exceptions\DiscordRequestException;
use App\Services\Discord\Exceptions\MessageNotFoundException;
use App\Services\Discord\Notifications\Driver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverTest extends TestCase
{
    use RefreshDatabase;

    private Discord&MockInterface $discord;

    private Driver $driver;

    private ChannelContract $channel;

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

        $notification = Mockery::mock(Notification::class);
        $notification->updates = null;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
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

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '222222222222222222')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($existingDiscordMessage, $payload)
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

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturn($user);

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '333333333333333333')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($existingDiscordMessage, $payload)
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

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $existingNotification;
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

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $staleNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
        $notification->expects('sender')->never();

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '444444444444444444')
            ->andThrow(new MessageNotFoundException('GET', 'channels/987654321098765432/messages/444444444444444444', 404));

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

    #[Test]
    public function send_falls_through_to_create_when_edit_message_throws_message_not_found_exception(): void
    {
        $payload = MessagePayload::from(['content' => 'Recovered via edit failure!']);

        $staleNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '777777777777777777',
        ]);

        $existingDiscordMessage = $this->makeDiscordMessage('777777777777777777');

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $staleNotification;
        $notification->expects('toMessage')->twice()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
        $notification->expects('sender')->never();

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '777777777777777777')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($existingDiscordMessage, $payload)
            ->andThrow(new MessageNotFoundException('PATCH', 'channels/987654321098765432/messages/777777777777777777', 404));

        $this->discord->expects('createMessage')
            ->with($this->channel, $payload)
            ->andReturn($this->makeDiscordMessage('888888888888888888'));

        $this->driver->send($this->notifiable, $notification);

        $this->assertSoftDeleted('discord_notifications', ['id' => $staleNotification->id]);
        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '888888888888888888',
            'channel_id' => $this->channel->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // send — related models synced
    // -------------------------------------------------------------------------

    #[Test]
    public function it_syncs_related_models_when_creating_a_new_notification(): void
    {
        [$userA, $userB] = User::factory()->count(2)->create();
        $payload = MessagePayload::from(['content' => 'With related models']);

        $notification = Mockery::mock(Notification::class);
        $notification->updates = null;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $notification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userA->id, 'model_type' => User::class],
            ['model_id' => $userB->id, 'model_type' => User::class],
        ]);

        $this->discord->expects('createMessage')
            ->andReturn($this->makeDiscordMessage('100000000000000001'));

        $this->driver->send($this->notifiable, $notification);

        $record = DiscordNotification::where('message_id', '100000000000000001')->first();

        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record->id,
            'model_type' => User::class,
            'model_id' => $userA->id,
        ]);
        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record->id,
            'model_type' => User::class,
            'model_id' => $userB->id,
        ]);
    }

    #[Test]
    public function it_syncs_related_models_for_each_new_notification_independently(): void
    {
        [$userA, $userB, $userC] = User::factory()->count(3)->create();
        $payload = MessagePayload::from(['content' => 'Related model isolation test']);

        $firstNotification = Mockery::mock(Notification::class);
        $firstNotification->updates = null;
        $firstNotification->expects('toMessage')->once()->andReturn($payload);
        $firstNotification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $firstNotification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userA->id, 'model_type' => User::class],
            ['model_id' => $userB->id, 'model_type' => User::class],
        ]);

        $this->discord->expects('createMessage')
            ->once()
            ->andReturn($this->makeDiscordMessage('200000000000000001'));

        $this->driver->send($this->notifiable, $firstNotification);

        $secondNotification = Mockery::mock(Notification::class);
        $secondNotification->updates = null;
        $secondNotification->expects('toMessage')->once()->andReturn($payload);
        $secondNotification->expects('toDatabase')->once()->with($this->notifiable)->andReturn([
            'type' => 'App\\Notifications\\DailyQuestsMessage',
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ]);
        $secondNotification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userB->id, 'model_type' => User::class],
            ['model_id' => $userC->id, 'model_type' => User::class],
        ]);

        $this->discord->expects('createMessage')
            ->once()
            ->andReturn($this->makeDiscordMessage('200000000000000002'));

        $this->driver->send($this->notifiable, $secondNotification);

        $record1 = DiscordNotification::where('message_id', '200000000000000001')->first();
        $record2 = DiscordNotification::where('message_id', '200000000000000002')->first();

        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record1->id,
            'model_type' => User::class,
            'model_id' => (string) $userA->id,
        ]);
        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record1->id,
            'model_type' => User::class,
            'model_id' => (string) $userB->id,
        ]);

        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record2->id,
            'model_type' => User::class,
            'model_id' => (string) $userB->id,
        ]);
        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $record2->id,
            'model_type' => User::class,
            'model_id' => (string) $userC->id,
        ]);

        $this->assertDatabaseMissing('discord_notification_related_models', [
            'discord_notification_id' => $record2->id,
            'model_type' => User::class,
            'model_id' => (string) $userA->id,
        ]);
    }

    #[Test]
    public function send_propagates_discord_request_exception_without_deleting_the_record(): void
    {
        $payload = MessagePayload::from(['content' => 'Will fail on edit']);

        $existingNotification = DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => '999999999999999999',
        ]);

        $existingDiscordMessage = $this->makeDiscordMessage('999999999999999999');

        $notification = Mockery::mock(Notification::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);

        $this->discord->expects('getChannelMessage')
            ->with($this->channel, '999999999999999999')
            ->andReturn($existingDiscordMessage);

        $this->discord->expects('editMessage')
            ->with($existingDiscordMessage, $payload)
            ->andThrow(new DiscordRequestException('PATCH', 'channels/987654321098765432/messages/999999999999999999', 500));

        $this->discord->expects('createMessage')->never();

        $this->expectException(DiscordRequestException::class);

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', ['id' => $existingNotification->id]);
    }
}
