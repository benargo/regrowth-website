<?php

namespace Tests\Unit\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use App\Contracts\HasCharacterMedia;
use App\Models\Boss;
use App\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tests\TestCase;

class UrlGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function it_returns_signed_icons_show_url_for_has_blizzard_icons_media(): void
    {
        $model = BlizzardIconStubModel::create(['title' => 'Test', 'type' => 'role']);

        $model->addMediaFromString('BINARY')
            ->usingFileName('inv_shield_04.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $url = $model->getFirstMediaUrl('blizzard_icons');

        $this->assertStringContainsString('/icons/56/inv_shield_04.jpg', $url);
        $this->assertTrue(URL::hasValidSignature(request()->create($url)));
    }

    #[Test]
    public function it_uses_the_custom_size_in_the_signed_url(): void
    {
        $model = BlizzardIconStubModel::create(['title' => 'Test', 'type' => 'role']);

        $model->addMediaFromString('BINARY')
            ->usingFileName('inv_shield_04.jpg')
            ->withCustomProperties(['size' => 36])
            ->toMediaCollection('blizzard_icons');

        $url = $model->getFirstMediaUrl('blizzard_icons');

        $this->assertStringContainsString('/icons/36/inv_shield_04.jpg', $url);
        $this->assertTrue(URL::hasValidSignature(request()->create($url)));
    }

    #[Test]
    public function it_returns_default_storage_url_for_non_blizzard_media(): void
    {
        $boss = Boss::factory()->create();

        $boss->addMediaFromString('BINARY')
            ->usingFileName('screenshot.jpg')
            ->toMediaCollection();

        $url = $boss->getFirstMediaUrl();

        $this->assertStringContainsString('/storage/', $url);
        $this->assertStringNotContainsString('/icons/', $url);
    }

    #[Test]
    public function it_returns_storage_url_for_has_character_media_model(): void
    {
        $character = Character::factory()->create();

        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->withCustomProperties(['size' => HasCharacterMedia::DEFAULT_MEDIA_SIZE])
            ->toMediaCollection('character_portraits');

        $url = $character->getFirstMediaUrl('character_portraits');

        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/', $url);
        $this->assertStringContainsString('character_15678.jpg', $url);
        $this->assertStringNotContainsString('/icons/', $url);
    }

    #[Test]
    public function it_returns_null_for_has_character_media_with_no_media(): void
    {
        $character = Character::factory()->create();

        $url = $character->getFirstMediaUrl('character_portraits') ?: null;

        $this->assertNull($url);
    }

    #[Test]
    public function it_returns_default_storage_url_for_has_blizzard_icons_model_with_non_blizzard_collection(): void
    {
        $model = BlizzardIconStubModel::create(['title' => 'Test', 'type' => 'role']);

        $model->addMediaFromString('BINARY')
            ->usingFileName('screenshot.jpg')
            ->toMediaCollection('other');

        $url = $model->getFirstMediaUrl('other');

        $this->assertStringContainsString('/storage/', $url);
        $this->assertStringNotContainsString('/icons/', $url);
    }
}

/**
 * Lightweight test double that implements HasBlizzardIcons so the positive-branch
 * of UrlGenerator can be exercised before any production model adopts
 * the interface. Backed by the `lootcouncil_priorities` table (title + type columns);
 * reuses an existing migrated table with compatible columns to avoid a throwaway migration.
 */
class BlizzardIconStubModel extends Model implements HasBlizzardIcons, HasMedia
{
    use InteractsWithMedia;

    protected $table = 'lootcouncil_priorities';

    public $timestamps = true;

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('blizzard_icons')->singleFile();
        $this->addMediaCollection('other');
    }
}
