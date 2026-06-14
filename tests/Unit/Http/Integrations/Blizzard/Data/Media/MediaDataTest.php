<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Media;

use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class MediaDataTest extends TestCase
{
    #[Test]
    public function it_casts_response_with_multiple_assets(): void
    {
        $dto = MediaData::from([
            'id' => 19019,
            'assets' => [
                ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', 'file_data_id' => 132221],
                ['key' => 'zoom', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39_zoom.jpg', 'file_data_id' => 132222],
            ],
        ]);

        $this->assertInstanceOf(MediaData::class, $dto);
        $this->assertSame(19019, $dto->id);
        $this->assertCount(2, $dto->assets);
        $this->assertContainsOnlyInstancesOf(AssetData::class, $dto->assets);
        $this->assertSame('icon', $dto->assets[0]->key);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg', (string) $dto->assets[0]->value);
        $this->assertSame(132221, $dto->assets[0]->fileDataId);
        $this->assertSame('zoom', $dto->assets[1]->key);
        $this->assertSame(132222, $dto->assets[1]->fileDataId);
    }

    #[Test]
    public function it_casts_response_with_empty_assets(): void
    {
        $dto = MediaData::from([
            'id' => 999,
            'assets' => [],
        ]);

        $this->assertSame(999, $dto->id);
        $this->assertIsArray($dto->assets);
        $this->assertCount(0, $dto->assets);
    }

    #[Test]
    public function it_casts_asset_with_optional_fields_absent(): void
    {
        $dto = MediaData::from([
            'id' => 1,
            'assets' => [
                ['value' => 'https://render.worldofwarcraft.com/icons/56/spell_holy_flash.jpg'],
            ],
        ]);

        $this->assertSame(1, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertInstanceOf(Optional::class, $dto->assets[0]->key);
        $this->assertSame('https://render.worldofwarcraft.com/icons/56/spell_holy_flash.jpg', (string) $dto->assets[0]->value);
        $this->assertInstanceOf(Optional::class, $dto->assets[0]->fileDataId);
    }
}
