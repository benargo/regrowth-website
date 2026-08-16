<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Media;

use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class AssetDataTest extends TestCase
{
    #[Test]
    public function it_casts_full_asset_entry(): void
    {
        $dto = AssetData::from([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg',
            'file_data_id' => 642015,
        ]);

        $this->assertSame('icon', $dto->key);
        $this->assertInstanceOf(Uri::class, $dto->value);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', (string) $dto->value);
        $this->assertSame(642015, $dto->fileDataId);
    }

    #[Test]
    public function it_serialises_the_value_back_to_a_url_string(): void
    {
        $dto = AssetData::from([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg',
            'file_data_id' => 642015,
        ]);

        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', $dto->toArray()['value']);
    }

    #[Test]
    public function it_treats_missing_key_as_optional(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg',
            'file_data_id' => 132221,
        ]);

        $this->assertInstanceOf(Optional::class, $dto->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg', (string) $dto->value);
        $this->assertSame(132221, $dto->fileDataId);
    }

    #[Test]
    public function it_treats_missing_file_data_id_as_optional(): void
    {
        $dto = AssetData::from([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/druid.jpg',
        ]);

        $this->assertSame('icon', $dto->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/druid.jpg', (string) $dto->value);
        $this->assertInstanceOf(Optional::class, $dto->fileDataId);
    }

    #[Test]
    public function it_treats_both_key_and_file_data_id_as_optional(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/paladin.jpg',
        ]);

        $this->assertInstanceOf(Optional::class, $dto->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/paladin.jpg', (string) $dto->value);
        $this->assertInstanceOf(Optional::class, $dto->fileDataId);
    }

    #[Test]
    public function mirrored_path_returns_null_before_it_is_set(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
        ]);

        $this->assertNull($dto->mirroredPath());
    }

    #[Test]
    public function mirrored_path_returns_the_path_after_set(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
        ]);

        $dto->setMirroredPath('blizzard-cdn/icons/56/foo.jpg');

        $this->assertSame('blizzard-cdn/icons/56/foo.jpg', $dto->mirroredPath());
    }

    #[Test]
    public function mirrored_url_returns_null_when_path_not_set(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
        ]);

        $this->assertNull($dto->mirroredUrl());
    }

    #[Test]
    public function mirrored_url_returns_storage_url_when_path_is_set(): void
    {
        Storage::fake('public');

        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
        ]);

        $dto->setMirroredPath('blizzard-cdn/icons/56/foo.jpg');

        $this->assertStringContainsString('blizzard-cdn/icons/56/foo.jpg', $dto->mirroredUrl());
    }
}
