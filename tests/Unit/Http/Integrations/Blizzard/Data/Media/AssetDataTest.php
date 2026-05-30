<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Media;

use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

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
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', $dto->value);
        $this->assertSame(642015, $dto->fileDataId);
    }

    #[Test]
    public function it_treats_missing_key_as_optional(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg',
            'file_data_id' => 132221,
        ]);

        $this->assertInstanceOf(Optional::class, $dto->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/warrior.jpg', $dto->value);
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
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/druid.jpg', $dto->value);
        $this->assertInstanceOf(Optional::class, $dto->fileDataId);
    }

    #[Test]
    public function it_treats_both_key_and_file_data_id_as_optional(): void
    {
        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/paladin.jpg',
        ]);

        $this->assertInstanceOf(Optional::class, $dto->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/paladin.jpg', $dto->value);
        $this->assertInstanceOf(Optional::class, $dto->fileDataId);
    }
}
