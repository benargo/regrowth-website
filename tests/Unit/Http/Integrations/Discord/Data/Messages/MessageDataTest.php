<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\AttachmentData;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\MessageData;
use App\Http\Integrations\Discord\Data\Messages\MessageReferenceData;
use App\Http\Integrations\Discord\Data\Users\UserData;
use App\Http\Integrations\Discord\Enums\MessageFlag;
use App\Http\Integrations\Discord\Enums\MessageReferenceType;
use App\Http\Integrations\Discord\Enums\MessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class MessageDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_required_fields_from_snake_case_keys(): void
    {
        $message = MessageData::from($this->minimalPayload());

        $this->assertSame('334385199974967042', $message->id);
        $this->assertSame('290926798999357250', $message->channelId);
        $this->assertSame('2017-07-11T17:27:07.299000+00:00', $message->timestamp);
        $this->assertFalse($message->tts);
        $this->assertFalse($message->mentionEveryone);
        $this->assertSame([], $message->mentionRoles);
        $this->assertSame([], $message->attachments);
        $this->assertSame([], $message->embeds);
        $this->assertFalse($message->pinned);
        $this->assertSame(MessageType::Default, $message->type);
        $this->assertInstanceOf(UserData::class, $message->author);
        $this->assertSame('', $message->content);
        $this->assertNull($message->editedTimestamp);
        $this->assertSame([], $message->mentions);
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('alwaysPresentFields')]
    public function it_rejects_a_message_missing_an_always_present_field(string $field): void
    {
        $payload = $this->minimalPayload();
        unset($payload[$field]);

        $this->assertDataRejects(MessageData::class, $payload, $field);
    }

    #[Test]
    public function it_leaves_omitted_fields_as_optional(): void
    {
        $message = MessageData::from($this->minimalPayload());

        $this->assertInstanceOf(Optional::class, $message->webhookId);
        $this->assertInstanceOf(Optional::class, $message->flags);
        $this->assertInstanceOf(Optional::class, $message->messageReference);
        $this->assertInstanceOf(Optional::class, $message->referencedMessage);
        $this->assertInstanceOf(Optional::class, $message->applicationId);
        $this->assertInstanceOf(Optional::class, $message->position);
        $this->assertInstanceOf(Optional::class, $message->nonce);
    }

    #[Test]
    public function it_stores_combined_flags_as_a_single_integer(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'flags' => MessageFlag::SuppressEmbeds->value | MessageFlag::Ephemeral->value,
        ]);

        $this->assertSame(68, $message->flags);
    }

    // ==================== content ====================

    #[Test]
    public function it_stores_the_content(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'content' => 'Supa Hot']);

        $this->assertSame('Supa Hot', $message->content);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_null_content(): void
    {
        $this->assertDataRejects(MessageData::class, [...$this->minimalPayload(), 'content' => null], 'content');
    }

    #[Test]
    public function it_stores_the_edited_timestamp(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'edited_timestamp' => '2017-07-11T18:00:00.000000+00:00']);

        $this->assertSame('2017-07-11T18:00:00.000000+00:00', $message->editedTimestamp);
    }

    #[Test]
    public function it_accepts_a_null_edited_timestamp(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'edited_timestamp' => null]);

        $this->assertNull($message->editedTimestamp);
    }

    #[Test]
    public function it_stores_the_webhook_id(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'webhook_id' => '111122223333444455']);

        $this->assertSame('111122223333444455', $message->webhookId);
    }

    #[Test]
    public function it_stores_an_integer_nonce(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'nonce' => 42]);

        $this->assertSame(42, $message->nonce);
    }

    #[Test]
    public function it_stores_a_string_nonce(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'nonce' => 'abc123']);

        $this->assertSame('abc123', $message->nonce);
    }

    // ==================== author and mentions ====================

    #[Test]
    public function it_hydrates_the_author_into_user_data(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'author' => $this->userPayload('53908232506183680', 'Mason')]);

        $this->assertInstanceOf(UserData::class, $message->author);
        $this->assertSame('53908232506183680', $message->author->id);
        $this->assertSame('Mason', $message->author->username);
    }

    #[Test]
    public function it_hydrates_mentions_into_a_list_of_user_data(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'mentions' => [
                $this->userPayload('80351110224678912', 'Nelly'),
                $this->userPayload('53908232506183680', 'Mason'),
            ],
        ]);

        $this->assertCount(2, $message->mentions);
        $this->assertInstanceOf(UserData::class, $message->mentions[0]);
        $this->assertSame('Nelly', $message->mentions[0]->username);
        $this->assertSame('Mason', $message->mentions[1]->username);
    }

    // ==================== message reference ====================

    #[Test]
    public function it_hydrates_the_message_reference(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'type' => MessageType::Reply->value,
            'message_reference' => [
                'type' => MessageReferenceType::Default->value,
                'message_id' => '306588351130107906',
                'channel_id' => '290926798999357250',
                'guild_id' => '290926798999357249',
            ],
        ]);

        $this->assertInstanceOf(MessageReferenceData::class, $message->messageReference);
        $this->assertSame(MessageReferenceType::Default, $message->messageReference->type);
        $this->assertSame('306588351130107906', $message->messageReference->messageId);
    }

    #[Test]
    public function it_hydrates_the_referenced_message_into_message_data(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'type' => MessageType::Reply->value,
            'referenced_message' => [
                ...$this->minimalPayload(),
                'id' => '306588351130107906',
                'content' => 'Original message',
            ],
        ]);

        $this->assertInstanceOf(MessageData::class, $message->referencedMessage);
        $this->assertSame('306588351130107906', $message->referencedMessage->id);
        $this->assertSame('Original message', $message->referencedMessage->content);
    }

    #[Test]
    public function it_accepts_a_null_referenced_message(): void
    {
        $message = MessageData::from([...$this->minimalPayload(), 'referenced_message' => null]);

        $this->assertNull($message->referencedMessage);
    }

    // ==================== embeds and attachments ====================

    #[Test]
    public function it_hydrates_embeds_into_a_list_of_embed_data(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'embeds' => [
                ['title' => 'Hello, Embed!', 'description' => 'This is an embedded message.'],
            ],
        ]);

        $this->assertCount(1, $message->embeds);
        $this->assertInstanceOf(EmbedData::class, $message->embeds[0]);
        $this->assertSame('Hello, Embed!', $message->embeds[0]->title);
    }

    #[Test]
    public function it_hydrates_attachments_into_a_list_of_attachment_data(): void
    {
        $message = MessageData::from([
            ...$this->minimalPayload(),
            'attachments' => [
                [
                    'id' => '123456789012345678',
                    'filename' => 'file.png',
                    'size' => 1024,
                    'url' => 'https://cdn.discordapp.com/attachments/file.png',
                    'proxy_url' => 'https://media.discordapp.net/attachments/file.png',
                ],
            ],
        ]);

        $this->assertCount(1, $message->attachments);
        $this->assertInstanceOf(AttachmentData::class, $message->attachments[0]);
        $this->assertSame('file.png', $message->attachments[0]->filename);
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array{string}>
     */
    public static function alwaysPresentFields(): array
    {
        return [
            'author' => ['author'],
            'content' => ['content'],
            'edited_timestamp' => ['edited_timestamp'],
            'mentions' => ['mentions'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalPayload(): array
    {
        return [
            'id' => '334385199974967042',
            'channel_id' => '290926798999357250',
            'author' => $this->userPayload('53908232506183680', 'Mason'),
            'content' => '',
            'timestamp' => '2017-07-11T17:27:07.299000+00:00',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function userPayload(string $id, string $username): array
    {
        return [
            'id' => $id,
            'username' => $username,
            'discriminator' => '0',
            'flags' => 0,
            'public_flags' => 0,
        ];
    }
}
