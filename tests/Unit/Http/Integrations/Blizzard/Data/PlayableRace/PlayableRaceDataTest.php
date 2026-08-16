<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\PlayableRace;

use App\Http\Integrations\Blizzard\Data\PlayableRace\PlayableRaceData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class PlayableRaceDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            '_links' => [
                'self' => [
                    'href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2?namespace=static-2.5.5_65000-classicann-eu',
                ],
            ],
            'id' => 2,
            'name' => 'Orc',
            'gender_name' => [
                'male' => 'Orc',
                'female' => 'Orc',
            ],
            'faction' => [
                'type' => 'HORDE',
                'name' => 'Horde',
            ],
            'is_selectable' => true,
            'is_allied_race' => false,
            'playable_classes' => [
                [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/1?namespace=static-2.5.5_65000-classicann-eu'],
                    'name' => 'Warrior',
                    'id' => 1,
                ],
                [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7?namespace=static-2.5.5_65000-classicann-eu'],
                    'name' => 'Shaman',
                    'id' => 7,
                ],
            ],
            'racial_spells' => [
                [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/spell/20572?namespace=static-2.5.5_65000-classicann-eu'],
                    'name' => 'Blood Fury',
                    'id' => 20572,
                ],
            ],
        ];
    }

    #[Test]
    public function from_api_response_parses_index_response(): void
    {
        $vo = PlayableRaceData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/1'],
            'name' => 'Human',
            'id' => 1,
        ]);

        $this->assertSame(1, $vo->id);
        $this->assertSame('Human', $vo->name);
        $this->assertInstanceOf(Optional::class, $vo->genderName);
        $this->assertInstanceOf(Optional::class, $vo->faction);
        $this->assertInstanceOf(Optional::class, $vo->isSelectable);
        $this->assertInstanceOf(Optional::class, $vo->isAlliedRace);
        $this->assertInstanceOf(Optional::class, $vo->playableClasses);
        $this->assertInstanceOf(Optional::class, $vo->racialSpells);
    }

    #[Test]
    public function from_api_response_parses_full_response(): void
    {
        $vo = PlayableRaceData::from($this->sampleApiResponse());

        $this->assertSame(2, $vo->id);
        $this->assertSame('Orc', $vo->name);
        $this->assertSame(['male' => 'Orc', 'female' => 'Orc'], $vo->genderName);
        $this->assertSame('HORDE', $vo->faction['type']);
        $this->assertSame('Horde', $vo->faction['name']);
        $this->assertTrue($vo->isSelectable);
        $this->assertFalse($vo->isAlliedRace);
        $this->assertCount(2, $vo->playableClasses);
        $this->assertInstanceOf(LinkData::class, $vo->playableClasses[0]);
        $this->assertSame('Warrior', $vo->playableClasses[0]->name);
        $this->assertCount(1, $vo->racialSpells);
        $this->assertInstanceOf(LinkData::class, $vo->racialSpells[0]);
        $this->assertSame('Blood Fury', $vo->racialSpells[0]->name);
    }

    #[Test]
    public function from_api_response_handles_empty_racial_spells(): void
    {
        $data = $this->sampleApiResponse();
        $data['racial_spells'] = [];

        $vo = PlayableRaceData::from($data);

        $this->assertSame([], $vo->racialSpells);
    }

    #[Test]
    public function to_array_keys_use_snake_case(): void
    {
        $vo = PlayableRaceData::from($this->sampleApiResponse());

        $array = $vo->toArray();

        $this->assertArrayHasKey('gender_name', $array);
        $this->assertArrayHasKey('is_selectable', $array);
        $this->assertArrayHasKey('is_allied_race', $array);
        $this->assertArrayHasKey('playable_classes', $array);
        $this->assertArrayHasKey('racial_spells', $array);
        $this->assertArrayNotHasKey('genderName', $array);
        $this->assertArrayNotHasKey('isSelectable', $array);
    }
}
