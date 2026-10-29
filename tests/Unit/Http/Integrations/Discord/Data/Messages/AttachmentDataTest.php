<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\AttachmentData;
use App\Http\Integrations\Discord\Enums\AttachmentFlag;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class AttachmentDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_required_fields_from_snake_case_keys(): void
    {
        $attachment = AttachmentData::from($this->minimalPayload());

        $this->assertSame('123456789012345678', $attachment->id);
        $this->assertSame('file.png', $attachment->filename);
        $this->assertSame(1024, $attachment->size);
        $this->assertSame('https://cdn.discordapp.com/attachments/file.png', $attachment->url);
        $this->assertSame('https://media.discordapp.net/attachments/file.png', $attachment->proxyUrl);
    }

    #[Test]
    public function it_leaves_omitted_fields_as_optional(): void
    {
        $attachment = AttachmentData::from($this->minimalPayload());

        $this->assertInstanceOf(Optional::class, $attachment->title);
        $this->assertInstanceOf(Optional::class, $attachment->description);
        $this->assertInstanceOf(Optional::class, $attachment->contentType);
        $this->assertInstanceOf(Optional::class, $attachment->height);
        $this->assertInstanceOf(Optional::class, $attachment->width);
        $this->assertInstanceOf(Optional::class, $attachment->placeholder);
        $this->assertInstanceOf(Optional::class, $attachment->placeholderVersion);
        $this->assertInstanceOf(Optional::class, $attachment->ephemeral);
        $this->assertInstanceOf(Optional::class, $attachment->durationSecs);
        $this->assertInstanceOf(Optional::class, $attachment->waveform);
        $this->assertInstanceOf(Optional::class, $attachment->flags);
        $this->assertInstanceOf(Optional::class, $attachment->clipCreatedAt);
    }

    #[Test]
    public function it_hydrates_every_optional_field(): void
    {
        $attachment = AttachmentData::from([
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
            'flags' => 8,
            'clip_created_at' => '2024-01-01T00:00:00Z',
        ]);

        $this->assertSame('My File', $attachment->title);
        $this->assertSame('An image attachment', $attachment->description);
        $this->assertSame('image/png', $attachment->contentType);
        $this->assertSame(100, $attachment->height);
        $this->assertSame(200, $attachment->width);
        $this->assertSame('abc123', $attachment->placeholder);
        $this->assertSame(1, $attachment->placeholderVersion);
        $this->assertTrue($attachment->ephemeral);
        $this->assertSame(3.5, $attachment->durationSecs);
        $this->assertSame('AQID', $attachment->waveform);
        $this->assertSame(8, $attachment->flags);
        $this->assertSame('2024-01-01T00:00:00Z', $attachment->clipCreatedAt);
    }

    #[Test]
    public function it_stores_combined_flags_as_a_single_integer(): void
    {
        $attachment = AttachmentData::from([
            ...$this->minimalPayload(),
            'flags' => AttachmentFlag::IsSpoiler->value | AttachmentFlag::IsAnimated->value,
        ]);

        $this->assertSame(40, $attachment->flags);
    }

    #[Test]
    public function it_accepts_null_height_and_width(): void
    {
        $attachment = AttachmentData::from([
            ...$this->minimalPayload(),
            'height' => null,
            'width' => null,
        ]);

        $this->assertNull($attachment->height);
        $this->assertNull($attachment->width);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_title(): void
    {
        $this->assertDataRejects(AttachmentData::class, [...$this->minimalPayload(), 'title' => null], 'title');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_description_over_one_thousand_and_twenty_four_characters(): void
    {
        $this->assertDataRejects(AttachmentData::class, [...$this->minimalPayload(), 'description' => str_repeat('a', 1025)], 'description');
    }

    // ==================== helpers ====================

    /**
     * @return array<string, int|string>
     */
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
}
