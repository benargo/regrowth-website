<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\PlayableClass;

use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassData;
use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class PlayableClassDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7?namespace=static-2.5.5_65000-classicann-eu',
                ],
            ],
            'id' => 7,
            'name' => 'Shaman',
            'gender_name' => [
                'male' => 'Shaman',
                'female' => 'Shaman',
            ],
            'power_type' => [
                'key' => [
                    'href' => 'https://eu.api.blizzard.com/data/wow/power-type/0?namespace=static-2.5.5_65000-classicann-eu',
                ],
                'name' => 'Mana',
                'id' => 0,
            ],
            'media' => [
                'key' => [
                    'href' => 'https://eu.api.blizzard.com/data/wow/media/playable-class/7?namespace=static-2.5.5_65000-classicann-eu',
                ],
                'id' => 7,
            ],
            'pvp_talent_slots' => [
                'href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7/pvp-talent-slots?namespace=static-2.5.5_65000-classicann-eu',
            ],
            'playable_races' => [
                [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/6?namespace=static-2.5.5_65000-classicann-eu'],
                    'name' => 'Tauren',
                    'id' => 6,
                ],
                [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/11?namespace=static-2.5.5_65000-classicann-eu'],
                    'name' => 'Draenei',
                    'id' => 11,
                ],
            ],
        ];
    }

    #[Test]
    public function it_casts_full_api_response(): void
    {
        $dto = PlayableClassData::from($this->sampleApiResponse());

        $this->assertSame(7, $dto->id);
        $this->assertSame('Shaman', $dto->name);
        $this->assertSame(['male' => 'Shaman', 'female' => 'Shaman'], $dto->genderName);
        $this->assertInstanceOf(LinkData::class, $dto->powerType);
        $this->assertSame('Mana', $dto->powerType->name);
        $this->assertSame(0, $dto->powerType->id);
        $this->assertInstanceOf(LinkData::class, $dto->media);
        $this->assertSame(7, $dto->media->id);
        $this->assertInstanceOf(HrefData::class, $dto->pvpTalentSlots);
        $this->assertSame(
            'https://eu.api.blizzard.com/data/wow/playable-class/7/pvp-talent-slots?namespace=static-2.5.5_65000-classicann-eu',
            (string) $dto->pvpTalentSlots->href,
        );
        $this->assertCount(2, $dto->playableRaces);
        $this->assertInstanceOf(LinkData::class, $dto->playableRaces[0]);
        $this->assertSame(6, $dto->playableRaces[0]->id);
        $this->assertSame('Tauren', $dto->playableRaces[0]->name);
    }

    #[Test]
    public function it_handles_empty_playable_races(): void
    {
        $data = $this->sampleApiResponse();
        $data['playable_races'] = [];

        $dto = PlayableClassData::from($data);

        $this->assertSame([], $dto->playableRaces);
    }
}
