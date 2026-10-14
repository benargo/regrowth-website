<?php

namespace Tests\Feature\Notifications\Discord;

use App\Http\Integrations\Discord\Contracts\Channel as ChannelContract;
use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\GetMessageRequest;
use App\Models\DiscordNotification;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Notifications\Discord\DiscordChannel;
use App\Notifications\Discord\DiscordMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class DiscordChannelTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    private DiscordChannel $driver;

    private ChannelContract $channel;

    private ChannelData $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new DiscordChannel($this->app->make(DiscordConnector::class));

        $this->channel = ChannelData::from(['id' => '987654321098765432', 'type' => ChannelType::GuildText->value]);
        $this->notifiable = $this->channel;
    }

    #[Test]
    public function it_creates_a_new_discord_message_when_no_updates_notification_exists(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Hello!']);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = null;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
        $notification->expects('sender')->never();

        $this->fakeCreatedMessage('111111111111111111');

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '111111111111111111',
            'channel_id' => $this->channel->id,
        ]);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof CreateMessageRequest
            && $request->resolveEndpoint() === '/channels/987654321098765432/messages'
            && $request->body()->all() === ['content' => 'Hello!']);
    }

    // ==================== send — edit ====================

    #[Test]
    public function it_edits_the_existing_discord_message_when_a_message_id_is_known(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Updated!']);
        $existingNotification = $this->storedNotification('222222222222222222');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();
        $notification->expects('mapRelatedModels')->never();

        $this->fakeExistingMessage('222222222222222222');

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'id' => $existingNotification->id,
            'message_id' => '222222222222222222',
        ]);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetMessageRequest
            && $request->resolveEndpoint() === '/channels/987654321098765432/messages/222222222222222222');
        Saloon::assertSent(fn (Request $request): bool => $request instanceof EditMessageRequest
            && $request->resolveEndpoint() === '/channels/987654321098765432/messages/222222222222222222'
            && $request->body()->all() === ['content' => 'Updated!']);
    }

    #[Test]
    public function it_records_the_sender_when_editing_an_existing_message(): void
    {
        $user = User::factory()->create();
        $payload = MessagePayloadData::from(['content' => 'Edited by user']);
        $existingNotification = $this->storedNotification('333333333333333333');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturn($user);
        $notification->expects('mapRelatedModels')->never();

        $this->fakeExistingMessage('333333333333333333');

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notifications', [
            'id' => $existingNotification->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_call_create_message_when_edit_succeeds(): void
    {
        $payload = MessagePayloadData::from(['content' => 'No create needed']);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $this->storedNotification('666666666666666666');
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();
        $notification->expects('mapRelatedModels')->never();

        $this->fakeExistingMessage('666666666666666666');

        $this->driver->send($this->notifiable, $notification);

        Saloon::assertNotSent(CreateMessageRequest::class);
    }

    #[Test]
    public function it_syncs_related_models_when_editing_an_existing_message(): void
    {
        [$userA, $userB] = User::factory()->count(2)->create();
        $payload = MessagePayloadData::from(['content' => 'Edited with related models']);
        $existingNotification = $this->storedNotification('123456789012345678');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->relatedModels = []; // non-null: sync should run
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();
        $notification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userA->id, 'model_type' => User::class],
            ['model_id' => $userB->id, 'model_type' => User::class],
        ]);

        $this->fakeExistingMessage('123456789012345678');

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $existingNotification->id,
            'model_type' => User::class,
            'model_id' => (string) $userA->id,
        ]);
        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $existingNotification->id,
            'model_type' => User::class,
            'model_id' => (string) $userB->id,
        ]);
    }

    #[Test]
    public function it_does_not_sync_related_models_when_editing_and_related_models_is_null(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Edit, no model change']);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $this->storedNotification('112233445566778899');
        $notification->relatedModels = null; // null: skip sync entirely
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();
        $notification->expects('mapRelatedModels')->never();

        $this->fakeExistingMessage('112233445566778899');

        $this->driver->send($this->notifiable, $notification);
    }

    // ==================== send — fallback to create ====================

    #[Test]
    public function it_deletes_the_stale_db_record_and_creates_a_new_message_when_the_discord_message_no_longer_exists(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Recovered!']);
        $staleNotification = $this->storedNotification('444444444444444444');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $staleNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
        $notification->expects('sender')->never();

        $this->fakeDiscord([GetMessageRequest::class => $this->makeDiscordErrorResponse(404, 10008, 'Unknown Message')]);
        $this->fakeCreatedMessage('555555555555555555');

        $this->driver->send($this->notifiable, $notification);

        $this->assertSoftDeleted('discord_notifications', ['id' => $staleNotification->id]);
        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '555555555555555555',
            'channel_id' => $this->channel->id,
        ]);
        Saloon::assertNotSent(EditMessageRequest::class);
    }

    #[Test]
    public function send_falls_through_to_create_when_edit_message_throws_message_not_found_exception(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Recovered via edit failure!']);
        $staleNotification = $this->storedNotification('777777777777777777');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $staleNotification;
        $notification->expects('toMessage')->twice()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $notification->expects('mapRelatedModels')->once()->andReturn([]);
        $notification->expects('sender')->never();

        $this->fakeDiscord([
            GetMessageRequest::class => MockResponse::make($this->makeDiscordMessageResponse('777777777777777777', $this->channel->id)),
            EditMessageRequest::class => $this->makeDiscordErrorResponse(404, 10008, 'Unknown Message'),
        ]);
        $this->fakeCreatedMessage('888888888888888888');

        $this->driver->send($this->notifiable, $notification);

        $this->assertSoftDeleted('discord_notifications', ['id' => $staleNotification->id]);
        $this->assertDatabaseHas('discord_notifications', [
            'message_id' => '888888888888888888',
            'channel_id' => $this->channel->id,
        ]);
    }

    // ==================== related models ====================

    #[Test]
    public function it_syncs_related_models_when_creating_a_new_notification(): void
    {
        [$userA, $userB] = User::factory()->count(2)->create();
        $payload = MessagePayloadData::from(['content' => 'With related models']);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = null;
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $notification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userA->id, 'model_type' => User::class],
            ['model_id' => $userB->id, 'model_type' => User::class],
        ]);

        $this->fakeCreatedMessage('100000000000000001');

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
        $payload = MessagePayloadData::from(['content' => 'Related model isolation test']);

        $firstNotification = Mockery::mock(DiscordMessage::class);
        $firstNotification->updates = null;
        $firstNotification->expects('toMessage')->once()->andReturn($payload);
        $firstNotification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $firstNotification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userA->id, 'model_type' => User::class],
            ['model_id' => $userB->id, 'model_type' => User::class],
        ]);

        $this->fakeCreatedMessage('200000000000000001');
        $this->driver->send($this->notifiable, $firstNotification);

        $secondNotification = Mockery::mock(DiscordMessage::class);
        $secondNotification->updates = null;
        $secondNotification->expects('toMessage')->once()->andReturn($payload);
        $secondNotification->expects('toDatabase')->once()->with($this->notifiable)->andReturn($this->databaseRow($payload));
        $secondNotification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $userB->id, 'model_type' => User::class],
            ['model_id' => $userC->id, 'model_type' => User::class],
        ]);

        // A later fake for the same request class replaces the first one.
        $this->fakeCreatedMessage('200000000000000002');
        $this->driver->send($this->notifiable, $secondNotification);

        $record1 = DiscordNotification::where('message_id', '200000000000000001')->first();
        $record2 = DiscordNotification::where('message_id', '200000000000000002')->first();

        $this->assertDatabaseHas('discord_notification_related_models', ['discord_notification_id' => $record1->id, 'model_type' => User::class, 'model_id' => (string) $userA->id]);
        $this->assertDatabaseHas('discord_notification_related_models', ['discord_notification_id' => $record1->id, 'model_type' => User::class, 'model_id' => (string) $userB->id]);
        $this->assertDatabaseHas('discord_notification_related_models', ['discord_notification_id' => $record2->id, 'model_type' => User::class, 'model_id' => (string) $userB->id]);
        $this->assertDatabaseHas('discord_notification_related_models', ['discord_notification_id' => $record2->id, 'model_type' => User::class, 'model_id' => (string) $userC->id]);
        $this->assertDatabaseMissing('discord_notification_related_models', ['discord_notification_id' => $record2->id, 'model_type' => User::class, 'model_id' => (string) $userA->id]);
    }

    #[Test]
    public function it_does_not_throw_when_syncing_the_same_related_model_twice(): void
    {
        $user = User::factory()->create();
        $payload = MessagePayloadData::from(['content' => 'Edited with related models']);
        $existingNotification = $this->storedNotification('123456789012345679');

        $existingNotification->relatedModels()->create([
            'model_type' => User::class,
            'model_id' => (string) $user->id,
        ]);

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->relatedModels = [['model_id' => $user->id, 'model_type' => User::class]];
        $notification->expects('toMessage')->once()->andReturn($payload);
        $notification->expects('sender')->once()->andReturnNull();
        $notification->expects('mapRelatedModels')->once()->andReturn([
            ['model_id' => $user->id, 'model_type' => User::class],
        ]);

        $this->fakeExistingMessage('123456789012345679');

        $this->driver->send($this->notifiable, $notification);

        $this->assertDatabaseHas('discord_notification_related_models', [
            'discord_notification_id' => $existingNotification->id,
            'model_type' => User::class,
            'model_id' => (string) $user->id,
        ]);
    }

    // ==================== send — errors ====================

    #[Test]
    #[Group('error-handling')]
    public function send_propagates_discord_request_exception_without_deleting_the_record(): void
    {
        $payload = MessagePayloadData::from(['content' => 'Will fail on edit']);
        $existingNotification = $this->storedNotification('999999999999999999');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->once()->andReturn($payload);

        $this->fakeDiscord([
            GetMessageRequest::class => MockResponse::make($this->makeDiscordMessageResponse('999999999999999999', $this->channel->id)),
            EditMessageRequest::class => $this->makeDiscordErrorResponse(500, 0, '500: Internal Server Error'),
        ]);

        try {
            $this->driver->send($this->notifiable, $notification);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $this->assertSame(500, $exception->getDiscordStatus());
        }

        $this->assertNotSoftDeleted('discord_notifications', ['id' => $existingNotification->id]);
        Saloon::assertNotSent(CreateMessageRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_the_record_when_the_channel_is_unknown(): void
    {
        $existingNotification = $this->storedNotification('121212121212121212');

        $notification = Mockery::mock(DiscordMessage::class);
        $notification->updates = $existingNotification;
        $notification->expects('toMessage')->never();

        $this->fakeDiscord([GetMessageRequest::class => $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel')]);

        try {
            $this->driver->send($this->notifiable, $notification);
            $this->fail('Expected an ApiException for an unknown channel.');
        } catch (ApiException $exception) {
            $this->assertSame(10003, $exception->getDiscordCode());
        }

        $this->assertNotSoftDeleted('discord_notifications', ['id' => $existingNotification->id]);
        Saloon::assertNotSent(CreateMessageRequest::class);
    }

    // ==================== helpers ====================

    private function storedNotification(string $messageId): DiscordNotification
    {
        return DiscordNotification::factory()->create([
            'channel_id' => $this->channel->id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * The GET (existence check) and PATCH both succeed for this message.
     */
    private function fakeExistingMessage(string $messageId): void
    {
        $message = MockResponse::make($this->makeDiscordMessageResponse($messageId, $this->channel->id));

        $this->fakeDiscord([
            GetMessageRequest::class => $message,
            EditMessageRequest::class => $message,
        ]);
    }

    private function fakeCreatedMessage(string $messageId): void
    {
        $this->fakeDiscordMessageCreated($messageId, $this->channel->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRow(MessagePayloadData $payload): array
    {
        return [
            'type' => DailyQuestsMessage::class,
            'channel_id' => $this->channel->id,
            'payload' => $payload->toArray(),
            'created_by_user_id' => null,
        ];
    }
}
