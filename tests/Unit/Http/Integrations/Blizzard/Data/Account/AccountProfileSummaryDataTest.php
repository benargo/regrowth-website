<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Account;

use App\Http\Integrations\Blizzard\Data\Account\AccountCharacterData;
use App\Http\Integrations\Blizzard\Data\Account\AccountProfileSummaryData;
use App\Http\Integrations\Blizzard\Data\Account\WowAccountData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class AccountProfileSummaryDataTest extends TestCase
{
    #[Test]
    public function it_casts_an_account_profile_summary_with_multiple_wow_accounts(): void
    {
        $dto = AccountProfileSummaryData::from([
            '_links' => [
                'self' => ['href' => 'https://eu.api.blizzard.com/profile/user/wow?namespace=profile-classic-eu'],
            ],
            'id' => 42,
            'wow_accounts' => [
                [
                    'id' => 111,
                    'characters' => [
                        $this->characterPayload(1001, 'Thrall'),
                    ],
                ],
                [
                    'id' => 222,
                ],
            ],
        ]);

        $this->assertSame(42, $dto->id);
        $this->assertCount(2, $dto->wowAccounts);
        $this->assertContainsOnlyInstancesOf(WowAccountData::class, $dto->wowAccounts);
        $this->assertSame(111, $dto->wowAccounts[0]->id);
        $this->assertContainsOnlyInstancesOf(AccountCharacterData::class, $dto->wowAccounts[0]->characters);
        $this->assertSame('Thrall', $dto->wowAccounts[0]->characters[0]->name);
        $this->assertSame(222, $dto->wowAccounts[1]->id);
        $this->assertSame([], $dto->wowAccounts[1]->characters);
    }

    #[Test]
    public function it_casts_an_account_profile_summary_with_no_wow_accounts(): void
    {
        $dto = AccountProfileSummaryData::from([
            'id' => 42,
            'wow_accounts' => [],
        ]);

        $this->assertSame(42, $dto->id);
        $this->assertSame([], $dto->wowAccounts);
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
