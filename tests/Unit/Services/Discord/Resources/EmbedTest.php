<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Enums\EmbedType;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedAuthor;
use App\Services\Discord\Resources\EmbedField;
use App\Services\Discord\Resources\EmbedFooter;
use App\Services\Discord\Resources\EmbedMedia;
use App\Services\Discord\Resources\EmbedProvider;
use App\Services\Discord\Resources\EmbedVideo;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class EmbedTest extends TestCase
{
    // -------------------------------------------------------------------------
    // EmbedFooter
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_footer_constructs_with_required_text(): void
    {
        $footer = EmbedFooter::from(['text' => 'My Footer']);

        $this->assertSame('My Footer', $footer->text);
        $this->assertNull($footer->icon_url);
        $this->assertNull($footer->proxy_icon_url);
    }

    #[Test]
    public function embed_footer_stores_optional_fields(): void
    {
        $footer = EmbedFooter::from([
            'text' => 'Footer',
            'icon_url' => 'https://example.com/icon.png',
            'proxy_icon_url' => 'https://proxy.example.com/icon.png',
        ]);

        $this->assertSame('https://example.com/icon.png', $footer->icon_url);
        $this->assertSame('https://proxy.example.com/icon.png', $footer->proxy_icon_url);
    }

    #[Test]
    public function embed_footer_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedFooter::from(['text' => 'x']));
    }

    // -------------------------------------------------------------------------
    // EmbedMedia
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_media_constructs_with_required_url(): void
    {
        $media = EmbedMedia::from(['url' => 'https://example.com/image.png']);

        $this->assertSame('https://example.com/image.png', $media->url);
        $this->assertNull($media->proxy_url);
        $this->assertNull($media->height);
        $this->assertNull($media->width);
        $this->assertNull($media->content_type);
        $this->assertNull($media->placeholder);
        $this->assertNull($media->placeholder_version);
        $this->assertNull($media->description);
        $this->assertNull($media->flags);
    }

    #[Test]
    public function embed_media_stores_all_optional_fields(): void
    {
        $media = EmbedMedia::from([
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

        $this->assertSame('https://proxy.example.com/image.png', $media->proxy_url);
        $this->assertSame(100, $media->height);
        $this->assertSame(200, $media->width);
        $this->assertSame('image/png', $media->content_type);
        $this->assertSame('abc123', $media->placeholder);
        $this->assertSame(1, $media->placeholder_version);
        $this->assertSame('An image', $media->description);
        $this->assertSame(0, $media->flags);
    }

    #[Test]
    public function embed_media_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedMedia::from(['url' => 'https://example.com/img.png']));
    }

    // -------------------------------------------------------------------------
    // EmbedVideo
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_video_constructs_with_all_fields_optional(): void
    {
        $video = EmbedVideo::from([]);

        $this->assertNull($video->url);
        $this->assertNull($video->proxy_url);
        $this->assertNull($video->height);
        $this->assertNull($video->width);
        $this->assertNull($video->content_type);
        $this->assertNull($video->placeholder);
        $this->assertNull($video->placeholder_version);
        $this->assertNull($video->description);
        $this->assertNull($video->flags);
    }

    #[Test]
    public function embed_video_stores_all_fields(): void
    {
        $video = EmbedVideo::from([
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
        $this->assertSame('https://proxy.example.com/video.mp4', $video->proxy_url);
        $this->assertSame(720, $video->height);
        $this->assertSame(1280, $video->width);
        $this->assertSame('video/mp4', $video->content_type);
        $this->assertSame('xyz789', $video->placeholder);
        $this->assertSame(2, $video->placeholder_version);
        $this->assertSame('A video', $video->description);
        $this->assertSame(1, $video->flags);
    }

    #[Test]
    public function embed_video_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedVideo::from([]));
    }

    // -------------------------------------------------------------------------
    // EmbedProvider
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_provider_constructs_with_all_fields_optional(): void
    {
        $provider = EmbedProvider::from([]);

        $this->assertNull($provider->name);
        $this->assertNull($provider->url);
    }

    #[Test]
    public function embed_provider_stores_all_fields(): void
    {
        $provider = EmbedProvider::from([
            'name' => 'YouTube',
            'url' => 'https://youtube.com',
        ]);

        $this->assertSame('YouTube', $provider->name);
        $this->assertSame('https://youtube.com', $provider->url);
    }

    #[Test]
    public function embed_provider_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedProvider::from([]));
    }

    // -------------------------------------------------------------------------
    // EmbedAuthor
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_author_constructs_with_required_name(): void
    {
        $author = EmbedAuthor::from(['name' => 'John Doe']);

        $this->assertSame('John Doe', $author->name);
        $this->assertNull($author->url);
        $this->assertNull($author->icon_url);
        $this->assertNull($author->proxy_icon_url);
    }

    #[Test]
    public function embed_author_stores_all_optional_fields(): void
    {
        $author = EmbedAuthor::from([
            'name' => 'John Doe',
            'url' => 'https://example.com/author',
            'icon_url' => 'https://example.com/author.png',
            'proxy_icon_url' => 'https://proxy.example.com/author.png',
        ]);

        $this->assertSame('https://example.com/author', $author->url);
        $this->assertSame('https://example.com/author.png', $author->icon_url);
        $this->assertSame('https://proxy.example.com/author.png', $author->proxy_icon_url);
    }

    #[Test]
    public function embed_author_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedAuthor::from(['name' => 'x']));
    }

    // -------------------------------------------------------------------------
    // EmbedField
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_field_constructs_with_required_fields(): void
    {
        $field = EmbedField::from(['name' => 'Level', 'value' => '60']);

        $this->assertSame('Level', $field->name);
        $this->assertSame('60', $field->value);
        $this->assertNull($field->inline);
    }

    #[Test]
    public function embed_field_stores_inline_flag(): void
    {
        $field = EmbedField::from(['name' => 'Level', 'value' => '60', 'inline' => true]);

        $this->assertTrue($field->inline);
    }

    #[Test]
    public function embed_field_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(EmbedField::from(['name' => 'x', 'value' => 'y']));
    }

    // -------------------------------------------------------------------------
    // Embed (main)
    // -------------------------------------------------------------------------

    #[Test]
    public function embed_constructs_with_all_fields_optional(): void
    {
        $embed = Embed::from([]);

        $this->assertNull($embed->title);
        $this->assertNull($embed->type);
        $this->assertNull($embed->description);
        $this->assertNull($embed->url);
        $this->assertNull($embed->timestamp);
        $this->assertNull($embed->color);
        $this->assertNull($embed->footer);
        $this->assertNull($embed->image);
        $this->assertNull($embed->thumbnail);
        $this->assertNull($embed->video);
        $this->assertNull($embed->provider);
        $this->assertNull($embed->author);
        $this->assertNull($embed->fields);
        $this->assertNull($embed->flags);
    }

    #[Test]
    public function embed_stores_all_scalar_fields(): void
    {
        $embed = Embed::from([
            'title' => 'My Embed',
            'type' => EmbedType::Rich->value,
            'description' => 'An embed description',
            'url' => 'https://example.com',
            'timestamp' => '2024-01-01T00:00:00Z',
            'color' => 16711680,
            'flags' => 4,
        ]);

        $this->assertSame('My Embed', $embed->title);
        $this->assertSame(EmbedType::Rich, $embed->type);
        $this->assertSame('An embed description', $embed->description);
        $this->assertSame('https://example.com', $embed->url);
        $this->assertSame('2024-01-01T00:00:00Z', $embed->timestamp);
        $this->assertSame(16711680, $embed->color);
        $this->assertSame(4, $embed->flags);
    }

    #[Test]
    public function embed_hydrates_nested_objects(): void
    {
        $embed = Embed::from([
            'footer' => ['text' => 'Footer text'],
            'image' => ['url' => 'https://example.com/image.png'],
            'thumbnail' => ['url' => 'https://example.com/thumb.png'],
            'video' => ['url' => 'https://example.com/video.mp4'],
            'provider' => ['name' => 'YouTube'],
            'author' => ['name' => 'John Doe'],
        ]);

        $this->assertInstanceOf(EmbedFooter::class, $embed->footer);
        $this->assertSame('Footer text', $embed->footer->text);

        $this->assertInstanceOf(EmbedMedia::class, $embed->image);
        $this->assertSame('https://example.com/image.png', $embed->image->url);

        $this->assertInstanceOf(EmbedMedia::class, $embed->thumbnail);
        $this->assertSame('https://example.com/thumb.png', $embed->thumbnail->url);

        $this->assertInstanceOf(EmbedVideo::class, $embed->video);
        $this->assertSame('https://example.com/video.mp4', $embed->video->url);

        $this->assertInstanceOf(EmbedProvider::class, $embed->provider);
        $this->assertSame('YouTube', $embed->provider->name);

        $this->assertInstanceOf(EmbedAuthor::class, $embed->author);
        $this->assertSame('John Doe', $embed->author->name);
    }

    #[Test]
    public function embed_hydrates_fields_collection(): void
    {
        $embed = Embed::from([
            'fields' => [
                ['name' => 'Level', 'value' => '60'],
                ['name' => 'Class', 'value' => 'Druid', 'inline' => true],
            ],
        ]);

        $this->assertIsArray($embed->fields);
        $this->assertCount(2, $embed->fields);
        $this->assertInstanceOf(EmbedField::class, $embed->fields[0]);
        $this->assertSame('Level', $embed->fields[0]->name);
        $this->assertSame('60', $embed->fields[0]->value);
        $this->assertNull($embed->fields[0]->inline);
        $this->assertSame('Class', $embed->fields[1]->name);
        $this->assertTrue($embed->fields[1]->inline);
    }

    #[Test]
    public function embed_hydrates_all_embed_type_cases(): void
    {
        foreach (EmbedType::cases() as $case) {
            $embed = Embed::from(['type' => $case->value]);
            $this->assertSame($case, $embed->type);
        }
    }

    #[Test]
    public function embed_rules_caps_fields_at_twenty_five(): void
    {
        $rules = Embed::rules();

        $this->assertArrayHasKey('fields', $rules);
        $this->assertContains('max:25', $rules['fields']);
    }

    #[Test]
    public function embed_properties_are_readonly(): void
    {
        $this->assertAllPropertiesReadonly(Embed::from([]));
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function assertAllPropertiesReadonly(object $instance): void
    {
        $reflection = new ReflectionClass($instance);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} on {$reflection->getShortName()} should be readonly."
            );
        }
    }
}
