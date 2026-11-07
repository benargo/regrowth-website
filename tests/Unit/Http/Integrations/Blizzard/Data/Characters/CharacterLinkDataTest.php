<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterLinkData;
use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\RealmLinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class CharacterLinkDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_character_link_with_a_realm(): void
    {
        $dto = CharacterLinkData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy?namespace=profile-classicann-eu'],
            'name' => 'Wastedhippy',
            'id' => 51042439,
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/6409?namespace=dynamic-classicann-eu'],
                'name' => 'Thunderstrike',
                'id' => 6409,
                'slug' => 'thunderstrike',
            ],
        ]);

        $this->assertInstanceOf(HrefData::class, $dto->key);
        $this->assertSame('https://eu.api.blizzard.com/profile/wow/character/thunderstrike/wastedhippy?namespace=profile-classicann-eu', (string) $dto->key->href);
        $this->assertSame('Wastedhippy', $dto->name);
        $this->assertSame(51042439, $dto->id);
        $this->assertInstanceOf(RealmLinkData::class, $dto->realm);
        $this->assertSame('thunderstrike', $dto->realm->slug);
        $this->assertInstanceOf(Optional::class, $dto->surname);
    }

    #[Test]
    public function it_casts_a_character_link_with_a_surname_and_no_realm(): void
    {
        $dto = CharacterLinkData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/tholomew-blackwell?namespace=profile-forever-eu'],
            'name' => 'Tholomew',
            'id' => 2398315,
            'surname' => 'Blackwell',
        ]);

        $this->assertSame('Tholomew', $dto->name);
        $this->assertSame('Blackwell', $dto->surname);
        $this->assertInstanceOf(Optional::class, $dto->realm);
    }
}
