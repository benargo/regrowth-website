<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterProfileData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class CharacterProfileDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 123456,
            'name' => 'Thrall',
            'gender' => ['type' => 'MALE', 'name' => 'Male'],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2'],
                'name' => 'Orc',
                'id' => 2,
            ],
            'character_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7'],
                'name' => 'Shaman',
                'id' => 7,
            ],
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                'name' => 'Silvermoon',
                'id' => 1605,
            ],
            'level' => 60,
            'last_login_timestamp' => 1700000000,
            'average_item_level' => 450,
            'equipped_item_level' => 445,
            'active_spec' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-specialization/264'],
                'name' => 'Restoration',
                'id' => 264,
            ],
            'guild' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/guild/silvermoon/regrowth'],
                'name' => 'Regrowth',
                'id' => 1,
            ],
            'experience' => 8400,
            'achievement_points' => 12500,
        ];
    }

    #[Test]
    public function it_casts_a_full_api_response(): void
    {
        $dto = CharacterProfileData::from($this->sampleApiResponse());

        $this->assertSame(123456, $dto->id);
        $this->assertSame('Thrall', $dto->name);
        $this->assertSame(['type' => 'MALE', 'name' => 'Male'], $dto->gender);
        $this->assertSame(['type' => 'HORDE', 'name' => 'Horde'], $dto->faction);
        $this->assertInstanceOf(LinkData::class, $dto->race);
        $this->assertSame('Orc', $dto->race->name);
        $this->assertSame(2, $dto->race->id);
        $this->assertInstanceOf(LinkData::class, $dto->characterClass);
        $this->assertSame('Shaman', $dto->characterClass->name);
        $this->assertSame(7, $dto->characterClass->id);
        $this->assertInstanceOf(LinkData::class, $dto->realm);
        $this->assertSame('Silvermoon', $dto->realm->name);
        $this->assertSame(60, $dto->level);
        $this->assertSame(1700000000, $dto->lastLoginTimestamp);
        $this->assertSame(450, $dto->averageItemLevel);
        $this->assertSame(445, $dto->equippedItemLevel);
        $this->assertInstanceOf(LinkData::class, $dto->activeSpec);
        $this->assertSame('Restoration', $dto->activeSpec->name);
        $this->assertSame(264, $dto->activeSpec->id);
        $this->assertInstanceOf(LinkData::class, $dto->guild);
        $this->assertSame('Regrowth', $dto->guild->name);
        $this->assertSame(8400, $dto->experience);
        $this->assertSame(12500, $dto->achievementPoints);
    }

    #[Test]
    public function it_casts_response_with_optional_fields_absent(): void
    {
        $data = $this->sampleApiResponse();
        unset($data['active_spec'], $data['guild'], $data['experience'], $data['achievement_points']);

        $dto = CharacterProfileData::from($data);

        $this->assertSame(123456, $dto->id);
        $this->assertSame(60, $dto->level);
        $this->assertInstanceOf(Optional::class, $dto->activeSpec);
        $this->assertInstanceOf(Optional::class, $dto->guild);
        $this->assertInstanceOf(Optional::class, $dto->experience);
        $this->assertInstanceOf(Optional::class, $dto->achievementPoints);
    }
}
