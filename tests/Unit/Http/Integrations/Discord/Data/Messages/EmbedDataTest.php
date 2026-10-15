<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedAuthorData;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFieldData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFooterData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\EmbedProviderData;
use App\Http\Integrations\Discord\Data\Messages\EmbedVideoData;
use App\Http\Integrations\Discord\Enums\EmbedType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_leaves_every_omitted_field_optional(): void
    {
        $embed = EmbedData::from([]);

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
    public function it_hydrates_every_scalar_field(): void
    {
        $embed = EmbedData::from([
            'title' => 'My Embed',
            'type' => 'rich',
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
    public function it_resolves_every_embed_type_from_its_wire_value(): void
    {
        foreach (EmbedType::cases() as $case) {
            $this->assertSame($case, EmbedData::from(['type' => $case->value])->type);
        }
    }

    #[Test]
    public function it_hydrates_nested_objects_into_their_data_classes(): void
    {
        $embed = EmbedData::from([
            'footer' => ['text' => 'Footer text'],
            'image' => ['url' => 'https://example.com/image.png'],
            'thumbnail' => ['url' => 'https://example.com/thumb.png'],
            'video' => ['url' => 'https://example.com/video.mp4'],
            'provider' => ['name' => 'YouTube'],
            'author' => ['name' => 'John Doe'],
        ]);

        $this->assertInstanceOf(EmbedFooterData::class, $embed->footer);
        $this->assertSame('Footer text', $embed->footer->text);

        $this->assertInstanceOf(EmbedMediaData::class, $embed->image);
        $this->assertSame('https://example.com/image.png', $embed->image->url);

        $this->assertInstanceOf(EmbedMediaData::class, $embed->thumbnail);
        $this->assertSame('https://example.com/thumb.png', $embed->thumbnail->url);

        $this->assertInstanceOf(EmbedVideoData::class, $embed->video);
        $this->assertSame('https://example.com/video.mp4', $embed->video->url);

        $this->assertInstanceOf(EmbedProviderData::class, $embed->provider);
        $this->assertSame('YouTube', $embed->provider->name);

        $this->assertInstanceOf(EmbedAuthorData::class, $embed->author);
        $this->assertSame('John Doe', $embed->author->name);
    }

    #[Test]
    public function it_hydrates_fields_into_a_list_of_field_data(): void
    {
        $embed = EmbedData::from([
            'fields' => [
                ['name' => 'Level', 'value' => '60'],
                ['name' => 'Class', 'value' => 'Druid', 'inline' => true],
            ],
        ]);

        $this->assertCount(2, $embed->fields);
        $this->assertInstanceOf(EmbedFieldData::class, $embed->fields[0]);
        $this->assertSame('Level', $embed->fields[0]->name);
        $this->assertSame('Class', $embed->fields[1]->name);
        $this->assertTrue($embed->fields[1]->inline);
    }

    #[Test]
    public function it_resolves_a_components_embed_type(): void
    {
        $this->assertSame(EmbedType::Components, EmbedData::from(['type' => 'components'])->type);
    }

    #[Test]
    #[Group('contract')]
    public function it_hydrates_an_explicit_null_footer_and_image_as_optional(): void
    {
        $embed = EmbedData::from(['title' => 'Daily', 'footer' => null, 'image' => null]);

        $this->assertInstanceOf(Optional::class, $embed->footer);
        $this->assertInstanceOf(Optional::class, $embed->image);
        $this->assertSame(['title' => 'Daily'], $embed->toArray());
    }

    // ==================== validate ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_title(): void
    {
        $this->assertDataRejects(EmbedData::class, ['title' => null], 'title');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_footer(): void
    {
        $this->assertDataRejects(EmbedData::class, ['footer' => null], 'footer');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_image(): void
    {
        $this->assertDataRejects(EmbedData::class, ['image' => null], 'image');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_title_over_two_hundred_and_fifty_six_characters(): void
    {
        $this->assertDataRejects(EmbedData::class, ['title' => str_repeat('a', 257)], 'title');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_description_over_four_thousand_and_ninety_six_characters(): void
    {
        $this->assertDataRejects(EmbedData::class, ['description' => str_repeat('a', 4097)], 'description');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_more_than_twenty_five_fields(): void
    {
        $this->assertDataRejects(EmbedData::class, ['fields' => array_fill(0, 26, ['name' => 'Level', 'value' => '60'])], 'fields');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_fields_that_are_not_a_list(): void
    {
        $this->assertDataRejects(EmbedData::class, ['fields' => 'Level'], 'fields');
    }
}
