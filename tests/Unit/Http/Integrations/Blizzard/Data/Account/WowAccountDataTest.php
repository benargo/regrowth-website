<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Account;

use App\Http\Integrations\Blizzard\Data\Account\AccountCharacterData;
use App\Http\Integrations\Blizzard\Data\Account\WowAccountData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class WowAccountDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_wow_account_with_characters(): void
    {
        $dto = WowAccountData::from([
            'id' => 123456,
            'characters' => [
                $this->characterPayload(1001, 'Thrall'),
                $this->characterPayload(1002, 'Cairne'),
            ],
        ]);

        $this->assertSame(123456, $dto->id);
        $this->assertCount(2, $dto->characters);
        $this->assertContainsOnlyInstancesOf(AccountCharacterData::class, $dto->characters);
        $this->assertSame('Thrall', $dto->characters[0]->name);
        $this->assertSame(1001, $dto->characters[0]->id);
        $this->assertSame('Cairne', $dto->characters[1]->name);
        $this->assertSame(1002, $dto->characters[1]->id);
    }

    #[Test]
    public function it_defaults_characters_to_an_empty_array_when_missing(): void
    {
        $dto = WowAccountData::from([
            'id' => 123456,
        ]);

        $this->assertSame(123456, $dto->id);
        $this->assertSame([], $dto->characters);
    }

    /**
     * @return array<string, mixed>
     */
    private function characterPayload(int $id, string $name): array
    {
        return [
            'character' => ['href' => "https://eu.api.blizzard.com/profile/wow/character/thunderstrike/{$id}"],
            'protected_character' => ['href' => "https://eu.api.blizzard.com/profile/user/wow/protected-character/1605-{$id}"],
            'name' => $name,
            'id' => $id,
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                'name' => 'Thunderstrike',
                'id' => 1605,
                'slug' => 'thunderstrike',
            ],
            'playable_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7'],
                'name' => 'Shaman',
                'id' => 7,
            ],
            'playable_race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2'],
                'name' => 'Orc',
                'id' => 2,
            ],
            'gender' => ['type' => 'MALE', 'name' => 'Male'],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'level' => 70,
        ];
    }
}
