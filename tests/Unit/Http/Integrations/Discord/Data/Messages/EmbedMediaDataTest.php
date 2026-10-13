<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedMediaDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_required_url_and_leaves_the_rest_optional(): void
    {
        $media = EmbedMediaData::from(['url' => 'https://example.com/image.png']);

        $this->assertSame('https://example.com/image.png', $media->url);
        $this->assertInstanceOf(Optional::class, $media->proxyUrl);
        $this->assertInstanceOf(Optional::class, $media->height);
        $this->assertInstanceOf(Optional::class, $media->width);
        $this->assertInstanceOf(Optional::class, $media->contentType);
        $this->assertInstanceOf(Optional::class, $media->placeholder);
        $this->assertInstanceOf(Optional::class, $media->placeholderVersion);
        $this->assertInstanceOf(Optional::class, $media->description);
        $this->assertInstanceOf(Optional::class, $media->flags);
    }

    #[Test]
    public function it_hydrates_every_optional_field(): void
    {
        $media = EmbedMediaData::from([
            'url' => 'https://example.com/image.png',
            'proxy_url' => 'https://proxy.example.com/image.png',
            'height' => 100,
            'width' => 200,
            'content_type' => 'image/png',
            'placeholder' => 'abc123',
            'placeholder_version' => 1,
            'description' => 'An image',
            'flags' => 0,
        ]);

        $this->assertSame('https://proxy.example.com/image.png', $media->proxyUrl);
        $this->assertSame(100, $media->height);
        $this->assertSame(200, $media->width);
        $this->assertSame('image/png', $media->contentType);
        $this->assertSame('abc123', $media->placeholder);
        $this->assertSame(1, $media->placeholderVersion);
        $this->assertSame('An image', $media->description);
        $this->assertSame(0, $media->flags);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_proxy_url(): void
    {
        $this->assertDataRejects(EmbedMediaData::class, ['url' => 'https://example.com/image.png', 'proxy_url' => null], 'proxy_url');
    }
}
