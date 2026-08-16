<?php

namespace Tests\Unit\Support\MediaLibrary;

use App\Models\Character;
use App\Support\MediaLibrary\CharacterMediaPathGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('media')]
#[Group('characters')]
class CharacterMediaPathGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function it_returns_character_portrait_path_with_realm_slug_and_size(): void
    {
        $media = $this->mediaFor('51042439-avatar.jpg', 135);

        $path = $this->generator('thunderstrike')->getPath($media);

        $this->assertSame('blizzard-cdn/characters/thunderstrike/135/51042439-avatar.jpg', $path);
    }

    #[Test]
    public function it_defaults_size_to_135_when_custom_property_absent(): void
    {
        $media = $this->mediaFor('51042439-avatar.jpg', null);

        $path = $this->generator()->getPath($media);

        $this->assertSame('blizzard-cdn/characters/thunderstrike/135/51042439-avatar.jpg', $path);
    }

    #[Test]
    public function it_uses_the_custom_size_in_the_path(): void
    {
        $media = $this->mediaFor('51042439-avatar.jpg', 56);

        $path = $this->generator()->getPath($media);

        $this->assertSame('blizzard-cdn/characters/thunderstrike/56/51042439-avatar.jpg', $path);
    }

    #[Test]
    public function it_uses_the_injected_realm_slug(): void
    {
        $media = $this->mediaFor('51042439-avatar.jpg', 135);

        $path = $this->generator('silvermoon')->getPath($media);

        $this->assertSame('blizzard-cdn/characters/silvermoon/135/51042439-avatar.jpg', $path);
    }

    #[Test]
    public function conversions_and_responsive_paths_nest_under_base_path(): void
    {
        $media = $this->mediaFor('51042439-avatar.jpg', 135);
        $generator = $this->generator();

        $this->assertSame('blizzard-cdn/characters/thunderstrike/135/conversions/', $generator->getPathForConversions($media));
        $this->assertSame('blizzard-cdn/characters/thunderstrike/135/responsive-images/', $generator->getPathForResponsiveImages($media));
    }

    #[Test]
    public function it_delegates_to_default_path_generator_for_non_character_portrait_collection(): void
    {
        $character = Character::factory()->create();

        $media = $character->addMediaFromString('BINARY')
            ->usingFileName('screenshot.jpg')
            ->toMediaCollection('other');

        $generator = $this->generator();

        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPath($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForConversions($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForResponsiveImages($media));
    }

    private function generator(string $realmSlug = 'thunderstrike'): CharacterMediaPathGenerator
    {
        return new CharacterMediaPathGenerator($realmSlug);
    }

    private function mediaFor(string $fileName, ?int $size): Media
    {
        $character = Character::factory()->create();

        return $character->addMediaFromString('BINARY')
            ->usingFileName($fileName)
            ->withCustomProperties($size === null ? [] : ['size' => $size])
            ->toMediaCollection('character_portraits');
    }
}
