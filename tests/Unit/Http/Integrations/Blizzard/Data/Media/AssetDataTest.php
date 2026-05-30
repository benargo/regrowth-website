<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Media;

use App\Contracts\Http\Integrations\Blizzard\MirrorsAssets;
use App\Facades\BlizzardAsset;
use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
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

    #[Test]
    public function it_implements_mirrors_assets(): void
    {
        $this->assertInstanceOf(
            MirrorsAssets::class,
            AssetData::from(['value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg']),
        );
    }

    #[Test]
    public function mirrored_url_resolves_through_the_facade_and_memoises(): void
    {
        Storage::fake('public');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
        BlizzardAsset::getFacadeRoot()->withMockClient($mock);

        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
            'key' => 'icon',
            'file_data_id' => 12345,
        ]);

        $url1 = $dto->mirroredUrl();
        $url2 = $dto->mirroredUrl();

        $this->assertNotNull($url1);
        $this->assertSame($url1, $url2);
        $this->assertStringContainsString('blizzard-cdn/icons/56/foo.jpg', $url1);
        $mock->assertSentCount(1);
    }

    #[Test]
    public function mirrored_path_returns_the_disk_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard-cdn/icons/56/foo.jpg', 'CACHED');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
        BlizzardAsset::getFacadeRoot()->withMockClient($mock);

        $dto = AssetData::from([
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
        ]);

        $this->assertSame('blizzard-cdn/icons/56/foo.jpg', $dto->mirroredPath());
    }
}
