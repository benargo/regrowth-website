<?php

namespace Tests\Unit\Services\Discord\Payloads;

use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Attachment;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\MessageReference;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class MessagePayloadTest extends TestCase
{
    #[Test]
    public function message_payload_constructs_with_all_fields_optional(): void
    {
        $payload = MessagePayload::from([]);

        $this->assertNull($payload->content);
        $this->assertNull($payload->nonce);
        $this->assertNull($payload->tts);
        $this->assertNull($payload->embeds);
        $this->assertNull($payload->message_reference);
        $this->assertNull($payload->sticker_ids);
        $this->assertNull($payload->payload_json);
        $this->assertNull($payload->attachments);
        $this->assertNull($payload->flags);
        $this->assertNull($payload->enforce_nonce);
    }

    #[Test]
    public function message_payload_stores_scalar_fields(): void
    {
        $payload = MessagePayload::from([
            'content' => 'Hello, world!',
            'nonce' => '987654321',
            'tts' => true,
            'sticker_ids' => ['111', '222'],
            'payload_json' => '{}',
            'flags' => 4,
            'enforce_nonce' => true,
        ]);

        $this->assertSame('Hello, world!', $payload->content);
        $this->assertSame('987654321', $payload->nonce);
        $this->assertTrue($payload->tts);
        $this->assertSame(['111', '222'], $payload->sticker_ids);
        $this->assertSame('{}', $payload->payload_json);
        $this->assertSame(4, $payload->flags);
        $this->assertTrue($payload->enforce_nonce);
    }

    #[Test]
    public function message_payload_accepts_integer_nonce(): void
    {
        $payload = MessagePayload::from(['nonce' => 12345]);

        $this->assertSame(12345, $payload->nonce);
    }

    #[Test]
    public function message_payload_hydrates_embeds_collection(): void
    {
        $payload = MessagePayload::from([
            'embeds' => [
                ['title' => 'First embed'],
                ['title' => 'Second embed'],
            ],
        ]);

        $this->assertIsArray($payload->embeds);
        $this->assertCount(2, $payload->embeds);
        $this->assertInstanceOf(Embed::class, $payload->embeds[0]);
        $this->assertSame('First embed', $payload->embeds[0]->title);
        $this->assertSame('Second embed', $payload->embeds[1]->title);
    }

    #[Test]
    public function message_payload_hydrates_message_reference(): void
    {
        $payload = MessagePayload::from([
            'message_reference' => ['message_id' => '111122223333444455'],
        ]);

        $this->assertInstanceOf(MessageReference::class, $payload->message_reference);
        $this->assertSame('111122223333444455', $payload->message_reference->message_id);
    }

    #[Test]
    public function message_payload_hydrates_attachments_collection(): void
    {
        $payload = MessagePayload::from([
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

        $this->assertIsArray($payload->attachments);
        $this->assertCount(1, $payload->attachments);
        $this->assertInstanceOf(Attachment::class, $payload->attachments[0]);
        $this->assertSame('file.png', $payload->attachments[0]->filename);
    }

    #[Test]
    public function message_payload_rules_cap_content_at_two_thousand_characters(): void
    {
        $rules = MessagePayload::rules();

        $this->assertArrayHasKey('content', $rules);
        $this->assertContains('max:2000', $rules['content']);
    }

    #[Test]
    public function message_payload_rules_cap_embeds_at_ten(): void
    {
        $rules = MessagePayload::rules();

        $this->assertArrayHasKey('embeds', $rules);
        $this->assertContains('max:10', $rules['embeds']);
    }

    #[Test]
    public function message_payload_rules_cap_sticker_ids_at_three(): void
    {
        $rules = MessagePayload::rules();

        $this->assertArrayHasKey('sticker_ids', $rules);
        $this->assertContains('max:3', $rules['sticker_ids']);
    }

    #[Test]
    public function message_payload_properties_are_readonly(): void
    {
        $payload = MessagePayload::from([]);
        $reflection = new ReflectionClass($payload);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on MessagePayload should be readonly."
            );
        }
    }
}
