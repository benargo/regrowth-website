<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterMediaData;
use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterMediaDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy/character-media?namespace=profile-classicann-eu',
                ],
            ],
            'character' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy?namespace=profile-classicann-eu'],
                'name' => 'Wastedhippy',
                'id' => 51042439,
            ],
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg'],
            ],
        ];
    }

    #[Test]
    public function it_casts_a_full_api_response(): void
    {
        $dto = CharacterMediaData::from($this->sampleApiResponse());

        $this->assertInstanceOf(CharacterMediaData::class, $dto);
        $this->assertInstanceOf(LinkData::class, $dto->character);
        $this->assertSame('Wastedhippy', $dto->character->name);
        $this->assertSame(51042439, $dto->character->id);
        $this->assertIsArray($dto->assets);
        $this->assertCount(1, $dto->assets);
        $this->assertInstanceOf(AssetData::class, $dto->assets[0]);
        $this->assertSame('avatar', $dto->assets[0]->key);
        $this->assertSame(
            'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
            $dto->assets[0]->value,
        );
    }

    #[Test]
    public function it_casts_multiple_assets(): void
    {
        $data = $this->sampleApiResponse();
        $data['assets'][] = ['key' => 'main', 'value' => 'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-main.jpg'];
        $data['assets'][] = ['key' => 'main-raw', 'value' => 'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-main-raw.jpg'];

        $dto = CharacterMediaData::from($data);

        $this->assertCount(3, $dto->assets);
        $this->assertSame('main', $dto->assets[1]->key);
        $this->assertSame('main-raw', $dto->assets[2]->key);
    }
}
