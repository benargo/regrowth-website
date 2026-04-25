<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\MessageFlag;
use App\Services\Discord\Enums\MessageReferenceType;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Resources\Attachment;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\Message;
use App\Services\Discord\Resources\MessageReference;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class MessageTest extends TestCase
{
    private function minimalPayload(): array
    {
        return [
            'id' => '334385199974967042',
            'channel_id' => '290926798999357250',
            'timestamp' => '2017-07-11T17:27:07.299000+00:00',
            'tts' => false,
            'mention_everyone' => false,
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ];
    }

    #[Test]
    public function message_constructs_with_required_fields(): void
    {
        $message = Message::from($this->minimalPayload());

        $this->assertSame('334385199974967042', $message->id);
        $this->assertSame('290926798999357250', $message->channel_id);
        $this->assertSame('2017-07-11T17:27:07.299000+00:00', $message->timestamp);
        $this->assertFalse($message->tts);
        $this->assertFalse($message->mention_everyone);
        $this->assertSame([], $message->mention_roles);
        $this->assertSame([], $message->attachments);
        $this->assertSame([], $message->embeds);
        $this->assertFalse($message->pinned);
        $this->assertSame(MessageType::Default, $message->type);
    }

    #[Test]
    public function message_optional_fields_default_to_null(): void
    {
        $message = Message::from($this->minimalPayload());

        $this->assertNull($message->author);
        $this->assertNull($message->content);
        $this->assertNull($message->edited_timestamp);
        $this->assertNull($message->mentions);
        $this->assertNull($message->webhook_id);
        $this->assertNull($message->flags);
        $this->assertNull($message->message_reference);
        $this->assertNull($message->referenced_message);
        $this->assertNull($message->application_id);
        $this->assertNull($message->position);
        $this->assertNull($message->nonce);
    }

    #[Test]
    public function message_stores_content_and_author(): void
    {
        $message = Message::from([
            ...$this->minimalPayload(),
            'content' => 'Supa Hot',
        ]);

        $this->assertSame('Supa Hot', $message->content);
    }

    #[Test]
    public function message_stores_edited_timestamp(): void
    {
        $message = Message::from([
            ...$this->minimalPayload(),
            'edited_timestamp' => '2017-07-11T18:00:00.000000+00:00',
        ]);

        $this->assertSame('2017-07-11T18:00:00.000000+00:00', $message->edited_timestamp);
    }

    #[Test]
    public function message_stores_webhook_id(): void
    {
        $message = Message::from([
            ...$this->minimalPayload(),
            'webhook_id' => '111122223333444455',
        ]);

        $this->assertSame('111122223333444455', $message->webhook_id);
    }

    #[Test]
    public function message_stores_flags_as_bitfield(): void
    {
        $flags = MessageFlag::SUPPRESS_EMBEDS->value | MessageFlag::EPHEMERAL->value;

        $message = Message::from([
            ...$this->minimalPayload(),
            'flags' => $flags,
        ]);

        $this->assertSame($flags, $message->flags);
        $this->assertTrue(($message->flags & MessageFlag::SUPPRESS_EMBEDS->value) !== 0);
        $this->assertTrue(($message->flags & MessageFlag::EPHEMERAL->value) !== 0);
        $this->assertFalse(($message->flags & MessageFlag::CROSSPOSTED->value) !== 0);
    }

    #[Test]
    public function message_stores_message_reference(): void
    {
        $message = Message::from([
            ...$this->minimalPayload(),
            'type' => MessageType::Reply->value,
            'message_reference' => [
                'type' => MessageReferenceType::Default->value,
                'message_id' => '306588351130107906',
                'channel_id' => '290926798999357250',
                'guild_id' => '290926798999357249',
            ],
        ]);

        $this->assertInstanceOf(MessageReference::class, $message->message_reference);
        $this->assertSame(MessageReferenceType::Default, $message->message_reference->type);
        $this->assertSame('306588351130107906', $message->message_reference->message_id);
    }

    #[Test]
    public function message_stores_embeds(): void
    {
        $message = Message::from([
            ...$this->minimalPayload(),
            'embeds' => [
                ['title' => 'Hello, Embed!', 'description' => 'This is an embedded message.'],
            ],
        ]);

        $this->assertCount(1, $message->embeds);
        $this->assertInstanceOf(Embed::class, $message->embeds[0]);
        $this->assertSame('Hello, Embed!', $message->embeds[0]->title);
    }

    #[Test]
    public function message_stores_attachments(): void
    {
        $message = Message::from([
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
        $this->assertInstanceOf(Attachment::class, $message->attachments[0]);
        $this->assertSame('file.png', $message->attachments[0]->filename);
    }

    #[Test]
    public function message_stores_nonce_as_integer(): void
    {
        $message = Message::from([...$this->minimalPayload(), 'nonce' => 42]);

        $this->assertSame(42, $message->nonce);
    }

    #[Test]
    public function message_stores_nonce_as_string(): void
    {
        $message = Message::from([...$this->minimalPayload(), 'nonce' => 'abc123']);

        $this->assertSame('abc123', $message->nonce);
    }

    #[Test]
    public function message_type_enum_has_correct_backing_values(): void
    {
        $this->assertSame(0, MessageType::Default->value);
        $this->assertSame(19, MessageType::Reply->value);
        $this->assertSame(20, MessageType::ChatInputCommand->value);
        $this->assertSame(23, MessageType::ContextMenuCommand->value);
    }

    #[Test]
    public function message_flag_enum_has_correct_bit_values(): void
    {
        $this->assertSame(1, MessageFlag::CROSSPOSTED->value);
        $this->assertSame(2, MessageFlag::IS_CROSSPOST->value);
        $this->assertSame(4, MessageFlag::SUPPRESS_EMBEDS->value);
        $this->assertSame(64, MessageFlag::EPHEMERAL->value);
        $this->assertSame(4096, MessageFlag::SUPPRESS_NOTIFICATIONS->value);
        $this->assertSame(32768, MessageFlag::IS_COMPONENTS_V2->value);
    }

    #[Test]
    public function message_properties_are_readonly(): void
    {
        $message = Message::from($this->minimalPayload());
        $reflection = new ReflectionClass($message);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on Message should be readonly."
            );
        }
    }
}
