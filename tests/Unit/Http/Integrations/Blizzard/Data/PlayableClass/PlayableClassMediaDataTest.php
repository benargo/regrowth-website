<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\PlayableClass;

use App\Http\Integrations\Blizzard\Data\Media\MediaAssetData;
use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassMediaData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableClassMediaDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 7,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => 'https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg',
                    'file_data_id' => 642015,
                ],
            ],
        ];
    }

    #[Test]
    public function it_casts_full_api_response(): void
    {
        $dto = PlayableClassMediaData::from($this->sampleApiResponse());

        $this->assertSame(7, $dto->id);
        $this->assertCount(1, $dto->assets);
        $this->assertInstanceOf(MediaAssetData::class, $dto->assets[0]);
        $this->assertSame('icon', $dto->assets[0]->key);
        $this->assertSame('https://render.worldofwarcraft.com/eu/icons/56/shaman.jpg', $dto->assets[0]->value);
        $this->assertSame(642015, $dto->assets[0]->fileDataId);
    }

    #[Test]
    public function it_casts_multiple_assets(): void
    {
        $data = $this->sampleApiResponse();
        $data['assets'][] = [
            'key' => 'zoom',
            'value' => 'https://render.worldofwarcraft.com/eu/icons/56/shaman-zoom.jpg',
            'file_data_id' => 642016,
        ];

        $dto = PlayableClassMediaData::from($data);

        $this->assertCount(2, $dto->assets);
        $this->assertSame('zoom', $dto->assets[1]->key);
    }

    #[Test]
    public function it_handles_empty_assets(): void
    {
        $data = $this->sampleApiResponse();
        $data['assets'] = [];

        $dto = PlayableClassMediaData::from($data);

        $this->assertSame(7, $dto->id);
        $this->assertSame([], $dto->assets);
    }
}
