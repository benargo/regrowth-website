<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Account;

use App\Http\Integrations\Blizzard\Data\Account\AccountCharacterData;
use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Data\Shared\RealmLinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class AccountCharacterDataTest extends TestCase
{
    #[Test]
    public function it_casts_an_account_character(): void
    {
        $dto = AccountCharacterData::from([
            'character' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/thrall?namespace=profile-classic-eu'],
            'protected_character' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow/protected-character/1605-98765?namespace=profile-classic-eu'],
            'name' => 'Thrall',
            'id' => 98765,
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605?namespace=dynamic-classic-eu'],
                'name' => 'Thunderstrike',
                'id' => 1605,
                'slug' => 'thunderstrike',
            ],
            'playable_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7?namespace=static-classic-eu'],
                'name' => 'Shaman',
                'id' => 7,
            ],
            'playable_race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2?namespace=static-classic-eu'],
                'name' => 'Orc',
                'id' => 2,
            ],
            'gender' => ['type' => 'MALE', 'name' => 'Male'],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'level' => 70,
        ]);

        $this->assertInstanceOf(HrefData::class, $dto->character);
        $this->assertSame('https://eu.api.blizzard.com/profile/wow/character/thunderstrike/thrall?namespace=profile-classic-eu', (string) $dto->character->href);
        $this->assertInstanceOf(HrefData::class, $dto->protectedCharacter);
        $this->assertSame('https://eu.api.blizzard.com/profile/user/wow/protected-character/1605-98765?namespace=profile-classic-eu', (string) $dto->protectedCharacter->href);
        $this->assertSame('Thrall', $dto->name);
        $this->assertSame(98765, $dto->id);
        $this->assertInstanceOf(RealmLinkData::class, $dto->realm);
        $this->assertSame('thunderstrike', $dto->realm->slug);
        $this->assertInstanceOf(LinkData::class, $dto->playableClass);
        $this->assertSame(7, $dto->playableClass->id);
        $this->assertInstanceOf(LinkData::class, $dto->playableRace);
        $this->assertSame(2, $dto->playableRace->id);
        $this->assertSame(['type' => 'MALE', 'name' => 'Male'], $dto->gender);
        $this->assertSame(['type' => 'HORDE', 'name' => 'Horde'], $dto->faction);
        $this->assertSame(70, $dto->level);
    }
}
