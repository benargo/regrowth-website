<?php

namespace Tests\Unit\Support\MediaLibrary;

use App\Models\LootPriority;
use App\Support\MediaLibrary\BlizzardIconPathGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Exercises BlizzardIconPathGenerator directly because no production model implements
 * HasBlizzardIcons yet. Full end-to-end verification that PathGeneratorFactory::create()
 * dispatches to this generator is deferred to Task 6, once Priority implements the interface.
 */
#[Group('media')]
#[Group('blizzard-integration')]
class BlizzardIconPathGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function mediaFor(string $fileName, ?int $size): Media
    {
        $priority = LootPriority::factory()->create();

        return $priority->addMediaFromString('BINARY')
            ->usingFileName($fileName)
            ->withCustomProperties($size === null ? [] : ['size' => $size])
            ->toMediaCollection('blizzard_icons');
    }

    #[Test]
    public function it_returns_shared_icon_path_using_size_custom_property(): void
    {
        $media = $this->mediaFor('inv_shield_04.jpg', 56);

        $path = (new BlizzardIconPathGenerator)->getPath($media);

        $this->assertSame('blizzard-cdn/icons/56/inv_shield_04.jpg', $path);
    }

    #[Test]
    public function it_defaults_size_to_56_when_custom_property_absent(): void
    {
        $media = $this->mediaFor('inv_shield_04.jpg', null);

        $path = (new BlizzardIconPathGenerator)->getPath($media);

        $this->assertSame('blizzard-cdn/icons/56/inv_shield_04.jpg', $path);
    }

    #[Test]
    public function it_uses_the_custom_size_in_the_path(): void
    {
        $media = $this->mediaFor('inv_shield_04.jpg', 36);

        $path = (new BlizzardIconPathGenerator)->getPath($media);

        $this->assertSame('blizzard-cdn/icons/36/inv_shield_04.jpg', $path);
    }

    #[Test]
    public function conversions_and_responsive_paths_nest_under_base_path(): void
    {
        $media = $this->mediaFor('inv_shield_04.jpg', 56);
        $generator = new BlizzardIconPathGenerator;

        $this->assertSame('blizzard-cdn/icons/56/conversions/', $generator->getPathForConversions($media));
        $this->assertSame('blizzard-cdn/icons/56/responsive-images/', $generator->getPathForResponsiveImages($media));
    }

    #[Test]
    public function it_delegates_to_default_path_generator_for_non_blizzard_icons_collection(): void
    {
        $priority = LootPriority::factory()->create();

        $media = $priority->addMediaFromString('BINARY')
            ->usingFileName('screenshot.jpg')
            ->toMediaCollection('other');

        $generator = new BlizzardIconPathGenerator;

        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPath($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForConversions($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForResponsiveImages($media));
    }
}
