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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedTest extends TestCase
{
    #[Test]
    public function embed_footer_can_be_constructed_directly(): void
    {
        $footer = new EmbedFooter(
            text: 'My Footer',
            icon_url: Optional::create(),
            proxy_icon_url: Optional::create(),
        );

        $this->assertSame('My Footer', $footer->text);
        $this->assertInstanceOf(Optional::class, $footer->icon_url);
        $this->assertInstanceOf(Optional::class, $footer->proxy_icon_url);
    }

    #[Test]
    public function embed_footer_constructs_with_required_text(): void
    {
        $footer = EmbedFooter::from(['text' => 'My Footer']);

        $this->assertSame('My Footer', $footer->text);
        $this->assertInstanceOf(Optional::class, $footer->icon_url);
        $this->assertInstanceOf(Optional::class, $footer->proxy_icon_url);
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

    // ==================== embedMedia ====================

    #[Test]
    public function embed_media_can_be_constructed_directly(): void
    {
        $media = new EmbedMedia(
            url: 'https://example.com/image.png',
            proxy_url: Optional::create(),
            height: Optional::create(),
            width: Optional::create(),
            content_type: Optional::create(),
            placeholder: Optional::create(),
            placeholder_version: Optional::create(),
            description: Optional::create(),
            flags: Optional::create(),
        );

        $this->assertSame('https://example.com/image.png', $media->url);
        $this->assertInstanceOf(Optional::class, $media->proxy_url);
        $this->assertInstanceOf(Optional::class, $media->height);
        $this->assertInstanceOf(Optional::class, $media->width);
        $this->assertInstanceOf(Optional::class, $media->content_type);
        $this->assertInstanceOf(Optional::class, $media->placeholder);
        $this->assertInstanceOf(Optional::class, $media->placeholder_version);
        $this->assertInstanceOf(Optional::class, $media->description);
        $this->assertInstanceOf(Optional::class, $media->flags);
    }

    #[Test]
    public function embed_media_constructs_with_required_url(): void
    {
        $media = EmbedMedia::from(['url' => 'https://example.com/image.png']);

        $this->assertSame('https://example.com/image.png', $media->url);
        $this->assertInstanceOf(Optional::class, $media->proxy_url);
        $this->assertInstanceOf(Optional::class, $media->height);
        $this->assertInstanceOf(Optional::class, $media->width);
        $this->assertInstanceOf(Optional::class, $media->content_type);
        $this->assertInstanceOf(Optional::class, $media->placeholder);
        $this->assertInstanceOf(Optional::class, $media->placeholder_version);
        $this->assertInstanceOf(Optional::class, $media->description);
        $this->assertInstanceOf(Optional::class, $media->flags);
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

    // ==================== embedVideo ====================

    #[Test]
    public function embed_video_can_be_constructed_directly(): void
    {
        $video = new EmbedVideo(
            url: Optional::create(),
            proxy_url: Optional::create(),
            height: Optional::create(),
            width: Optional::create(),
            content_type: Optional::create(),
            placeholder: Optional::create(),
            placeholder_version: Optional::create(),
            description: Optional::create(),
            flags: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $video->url);
        $this->assertInstanceOf(Optional::class, $video->proxy_url);
        $this->assertInstanceOf(Optional::class, $video->height);
        $this->assertInstanceOf(Optional::class, $video->width);
        $this->assertInstanceOf(Optional::class, $video->content_type);
        $this->assertInstanceOf(Optional::class, $video->placeholder);
        $this->assertInstanceOf(Optional::class, $video->placeholder_version);
        $this->assertInstanceOf(Optional::class, $video->description);
        $this->assertInstanceOf(Optional::class, $video->flags);
    }

    #[Test]
    public function embed_video_constructs_with_all_fields_optional(): void
    {
        $video = EmbedVideo::from([]);

        $this->assertInstanceOf(Optional::class, $video->url);
        $this->assertInstanceOf(Optional::class, $video->proxy_url);
        $this->assertInstanceOf(Optional::class, $video->height);
        $this->assertInstanceOf(Optional::class, $video->width);
        $this->assertInstanceOf(Optional::class, $video->content_type);
        $this->assertInstanceOf(Optional::class, $video->placeholder);
        $this->assertInstanceOf(Optional::class, $video->placeholder_version);
        $this->assertInstanceOf(Optional::class, $video->description);
        $this->assertInstanceOf(Optional::class, $video->flags);
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

    // ==================== embedProvider ====================

    #[Test]
    public function embed_provider_can_be_constructed_directly(): void
    {
        $provider = new EmbedProvider(
            name: Optional::create(),
            url: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $provider->name);
        $this->assertInstanceOf(Optional::class, $provider->url);
    }

    #[Test]
    public function embed_provider_constructs_with_all_fields_optional(): void
    {
        $provider = EmbedProvider::from([]);

        $this->assertInstanceOf(Optional::class, $provider->name);
        $this->assertInstanceOf(Optional::class, $provider->url);
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

    // ==================== embedAuthor ====================

    #[Test]
    public function embed_author_can_be_constructed_directly(): void
    {
        $author = new EmbedAuthor(
            name: 'John Doe',
            url: Optional::create(),
            icon_url: Optional::create(),
            proxy_icon_url: Optional::create(),
        );

        $this->assertSame('John Doe', $author->name);
        $this->assertInstanceOf(Optional::class, $author->url);
        $this->assertInstanceOf(Optional::class, $author->icon_url);
        $this->assertInstanceOf(Optional::class, $author->proxy_icon_url);
    }

    #[Test]
    public function embed_author_constructs_with_required_name(): void
    {
        $author = EmbedAuthor::from(['name' => 'John Doe']);

        $this->assertSame('John Doe', $author->name);
        $this->assertInstanceOf(Optional::class, $author->url);
        $this->assertInstanceOf(Optional::class, $author->icon_url);
        $this->assertInstanceOf(Optional::class, $author->proxy_icon_url);
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

    // ==================== embedField ====================

    #[Test]
    public function embed_field_can_be_constructed_directly(): void
    {
        $field = new EmbedField(
            name: 'Level',
            value: '60',
            inline: Optional::create(),
        );

        $this->assertSame('Level', $field->name);
        $this->assertSame('60', $field->value);
        $this->assertInstanceOf(Optional::class, $field->inline);
    }

    #[Test]
    public function embed_field_constructs_with_required_fields(): void
    {
        $field = EmbedField::from(['name' => 'Level', 'value' => '60']);

        $this->assertSame('Level', $field->name);
        $this->assertSame('60', $field->value);
        $this->assertInstanceOf(Optional::class, $field->inline);
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

    // ==================== embed main construction and hydration ====================

    #[Test]
    public function embed_can_be_constructed_directly(): void
    {
        $embed = new Embed(
            title: Optional::create(),
            type: Optional::create(),
            description: Optional::create(),
            url: Optional::create(),
            timestamp: Optional::create(),
            color: Optional::create(),
            footer: Optional::create(),
            image: Optional::create(),
            thumbnail: Optional::create(),
            video: Optional::create(),
            provider: Optional::create(),
            author: Optional::create(),
            fields: Optional::create(),
            flags: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $embed->title);
        $this->assertInstanceOf(Optional::class, $embed->type);
        $this->assertInstanceOf(Optional::class, $embed->description);
        $this->assertInstanceOf(Optional::class, $embed->url);
        $this->assertInstanceOf(Optional::class, $embed->timestamp);
        $this->assertInstanceOf(Optional::class, $embed->color);
        $this->assertInstanceOf(Optional::class, $embed->footer);
        $this->assertInstanceOf(Optional::class, $embed->image);
        $this->assertInstanceOf(Optional::class, $embed->thumbnail);
        $this->assertInstanceOf(Optional::class, $embed->video);
        $this->assertInstanceOf(Optional::class, $embed->provider);
        $this->assertInstanceOf(Optional::class, $embed->author);
        $this->assertInstanceOf(Optional::class, $embed->fields);
        $this->assertInstanceOf(Optional::class, $embed->flags);
    }

    #[Test]
    public function embed_constructs_with_all_fields_optional(): void
    {
        $embed = Embed::from([]);

        $this->assertInstanceOf(Optional::class, $embed->title);
        $this->assertInstanceOf(Optional::class, $embed->type);
        $this->assertInstanceOf(Optional::class, $embed->description);
        $this->assertInstanceOf(Optional::class, $embed->url);
        $this->assertInstanceOf(Optional::class, $embed->timestamp);
        $this->assertInstanceOf(Optional::class, $embed->color);
        $this->assertInstanceOf(Optional::class, $embed->footer);
        $this->assertInstanceOf(Optional::class, $embed->image);
        $this->assertInstanceOf(Optional::class, $embed->thumbnail);
        $this->assertInstanceOf(Optional::class, $embed->video);
        $this->assertInstanceOf(Optional::class, $embed->provider);
        $this->assertInstanceOf(Optional::class, $embed->author);
        $this->assertInstanceOf(Optional::class, $embed->fields);
        $this->assertInstanceOf(Optional::class, $embed->flags);
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
        $this->assertInstanceOf(Optional::class, $embed->fields[0]->inline);
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
    public function it_stores_null_for_nullable_optional_fields(): void
    {
        $embed = Embed::from(['image' => null]);
        $this->assertNull($embed->image);
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

    // ==================== helpers ====================

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
