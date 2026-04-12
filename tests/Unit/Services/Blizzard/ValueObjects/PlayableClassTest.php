<?php

namespace Tests\Unit\Services\Blizzard\ValueObjects;

use App\Services\Blizzard\ValueObjects\PlayableClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableClassTest extends TestCase
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
    public function from_api_response_parses_full_response(): void
    {
        $vo = PlayableClass::fromApiResponse($this->sampleApiResponse());

        $this->assertSame(7, $vo->id);
        $this->assertSame('Shaman', $vo->name);
        $this->assertSame(['male' => 'Shaman', 'female' => 'Shaman'], $vo->genderName);
        $this->assertSame(0, $vo->powerType['id']);
        $this->assertSame('Mana', $vo->powerType['name']);
        $this->assertSame(7, $vo->media['id']);
        $this->assertSame(
            'https://eu.api.blizzard.com/data/wow/playable-class/7/pvp-talent-slots?namespace=static-2.5.5_65000-classicann-eu',
            $vo->pvpTalentSlots['href'],
        );
        $this->assertCount(2, $vo->playableRaces);
        $this->assertSame(6, $vo->playableRaces[0]['id']);
        $this->assertSame('Tauren', $vo->playableRaces[0]['name']);
    }

    #[Test]
    public function from_api_response_handles_empty_playable_races(): void
    {
        $data = $this->sampleApiResponse();
        $data['playable_races'] = [];

        $vo = PlayableClass::fromApiResponse($data);

        $this->assertSame([], $vo->playableRaces);
    }

    #[Test]
    public function from_api_response_defaults_missing_keys_to_empty_arrays(): void
    {
        $vo = PlayableClass::fromApiResponse(['id' => 1, 'name' => 'Warrior']);

        $this->assertSame(1, $vo->id);
        $this->assertSame('Warrior', $vo->name);
        $this->assertSame([], $vo->genderName);
        $this->assertSame([], $vo->powerType);
        $this->assertSame([], $vo->media);
        $this->assertSame([], $vo->pvpTalentSlots);
        $this->assertSame([], $vo->playableRaces);
    }

    #[Test]
    public function to_array_round_trips_from_api_shape(): void
    {
        $data = $this->sampleApiResponse();
        unset($data['_links']);

        $vo = PlayableClass::fromApiResponse($data);

        $this->assertSame($data, $vo->toArray());
    }

    #[Test]
    public function to_array_keys_use_snake_case(): void
    {
        $vo = PlayableClass::fromApiResponse($this->sampleApiResponse());

        $array = $vo->toArray();

        $this->assertArrayHasKey('gender_name', $array);
        $this->assertArrayHasKey('power_type', $array);
        $this->assertArrayHasKey('pvp_talent_slots', $array);
        $this->assertArrayHasKey('playable_races', $array);
        $this->assertArrayNotHasKey('genderName', $array);
        $this->assertArrayNotHasKey('powerType', $array);
    }
}
