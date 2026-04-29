<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\AttachmentFlag;
use App\Services\Discord\Resources\Attachment;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    private function minimalPayload(): array
    {
        return [
            'id' => '123456789012345678',
            'filename' => 'file.png',
            'size' => 1024,
            'url' => 'https://cdn.discordapp.com/attachments/file.png',
            'proxy_url' => 'https://media.discordapp.net/attachments/file.png',
        ];
    }

    #[Test]
    public function attachment_constructs_with_required_fields(): void
    {
        $attachment = Attachment::from($this->minimalPayload());

        $this->assertSame('123456789012345678', $attachment->id);
        $this->assertSame('file.png', $attachment->filename);
        $this->assertSame(1024, $attachment->size);
        $this->assertSame('https://cdn.discordapp.com/attachments/file.png', $attachment->url);
        $this->assertSame('https://media.discordapp.net/attachments/file.png', $attachment->proxy_url);
    }

    #[Test]
    public function attachment_optional_fields_default_to_null(): void
    {
        $attachment = Attachment::from($this->minimalPayload());

        $this->assertNull($attachment->title);
        $this->assertNull($attachment->description);
        $this->assertNull($attachment->content_type);
        $this->assertNull($attachment->height);
        $this->assertNull($attachment->width);
        $this->assertNull($attachment->placeholder);
        $this->assertNull($attachment->placeholder_version);
        $this->assertNull($attachment->ephemeral);
        $this->assertNull($attachment->duration_secs);
        $this->assertNull($attachment->waveform);
        $this->assertNull($attachment->flags);
        $this->assertNull($attachment->clip_created_at);
    }

    #[Test]
    public function attachment_stores_all_optional_fields(): void
    {
        $attachment = Attachment::from([
            ...$this->minimalPayload(),
            'title' => 'My File',
            'description' => 'An image attachment',
            'content_type' => 'image/png',
            'height' => 100,
            'width' => 200,
            'placeholder' => 'abc123',
            'placeholder_version' => 1,
            'ephemeral' => true,
            'duration_secs' => 3.5,
            'waveform' => 'AQID',
            'flags' => AttachmentFlag::IS_SPOILER->value,
            'clip_created_at' => '2024-01-01T00:00:00Z',
        ]);

        $this->assertSame('My File', $attachment->title);
        $this->assertSame('An image attachment', $attachment->description);
        $this->assertSame('image/png', $attachment->content_type);
        $this->assertSame(100, $attachment->height);
        $this->assertSame(200, $attachment->width);
        $this->assertSame('abc123', $attachment->placeholder);
        $this->assertSame(1, $attachment->placeholder_version);
        $this->assertTrue($attachment->ephemeral);
        $this->assertSame(3.5, $attachment->duration_secs);
        $this->assertSame('AQID', $attachment->waveform);
        $this->assertSame(AttachmentFlag::IS_SPOILER->value, $attachment->flags);
        $this->assertSame('2024-01-01T00:00:00Z', $attachment->clip_created_at);
    }

    #[Test]
    public function attachment_flags_bitfield_combines_correctly(): void
    {
        $combinedFlags = AttachmentFlag::IS_SPOILER->value | AttachmentFlag::IS_ANIMATED->value;

        $attachment = Attachment::from([
            ...$this->minimalPayload(),
            'flags' => $combinedFlags,
        ]);

        $this->assertSame($combinedFlags, $attachment->flags);
        $this->assertTrue(($attachment->flags & AttachmentFlag::IS_SPOILER->value) !== 0);
        $this->assertTrue(($attachment->flags & AttachmentFlag::IS_ANIMATED->value) !== 0);
        $this->assertFalse(($attachment->flags & AttachmentFlag::IS_CLIP->value) !== 0);
    }

    #[Test]
    public function attachment_flag_enum_covers_all_bit_positions(): void
    {
        $this->assertSame(1, AttachmentFlag::IS_CLIP->value);
        $this->assertSame(2, AttachmentFlag::IS_THUMBNAIL->value);
        $this->assertSame(4, AttachmentFlag::IS_REMIX->value);
        $this->assertSame(8, AttachmentFlag::IS_SPOILER->value);
        $this->assertSame(16, AttachmentFlag::IS_ANIMATED->value);
    }

    #[Test]
    public function attachment_properties_are_readonly(): void
    {
        $attachment = Attachment::from($this->minimalPayload());
        $reflection = new ReflectionClass($attachment);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on Attachment should be readonly."
            );
        }
    }
}
