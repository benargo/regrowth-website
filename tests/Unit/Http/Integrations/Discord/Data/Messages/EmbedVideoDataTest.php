<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedVideoData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedVideoDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_leaves_every_omitted_field_optional(): void
    {
        $video = EmbedVideoData::from([]);

        $this->assertInstanceOf(Optional::class, $video->url);
        $this->assertInstanceOf(Optional::class, $video->proxyUrl);
        $this->assertInstanceOf(Optional::class, $video->height);
        $this->assertInstanceOf(Optional::class, $video->width);
        $this->assertInstanceOf(Optional::class, $video->contentType);
        $this->assertInstanceOf(Optional::class, $video->placeholder);
        $this->assertInstanceOf(Optional::class, $video->placeholderVersion);
        $this->assertInstanceOf(Optional::class, $video->description);
        $this->assertInstanceOf(Optional::class, $video->flags);
    }

    #[Test]
    public function it_hydrates_every_field(): void
    {
        $video = EmbedVideoData::from([
            'url' => 'https://example.com/video.mp4',
            'proxy_url' => 'https://proxy.example.com/video.mp4',
            'height' => 720,
            'width' => 1280,
            'content_type' => 'video/mp4',
            'placeholder' => 'xyz789',
            'placeholder_version' => 2,
            'description' => 'A video',
            'flags' => 1,
        ]);

        $this->assertSame('https://example.com/video.mp4', $video->url);
        $this->assertSame('https://proxy.example.com/video.mp4', $video->proxyUrl);
        $this->assertSame(720, $video->height);
        $this->assertSame(1280, $video->width);
        $this->assertSame('video/mp4', $video->contentType);
        $this->assertSame('xyz789', $video->placeholder);
        $this->assertSame(2, $video->placeholderVersion);
        $this->assertSame('A video', $video->description);
        $this->assertSame(1, $video->flags);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_url(): void
    {
        $this->assertDataRejects(EmbedVideoData::class, ['url' => null], 'url');
    }
}
