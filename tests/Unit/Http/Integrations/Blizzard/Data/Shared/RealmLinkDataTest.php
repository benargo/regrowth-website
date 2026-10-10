<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Shared;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\RealmLinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class RealmLinkDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_realm_link(): void
    {
        $dto = RealmLinkData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605?namespace=dynamic-classic-eu'],
            'name' => 'Thunderstrike',
            'id' => 1605,
            'slug' => 'thunderstrike',
        ]);

        $this->assertInstanceOf(HrefData::class, $dto->key);
        $this->assertSame('https://eu.api.blizzard.com/data/wow/realm/1605?namespace=dynamic-classic-eu', (string) $dto->key->href);
        $this->assertSame('Thunderstrike', $dto->name);
        $this->assertSame(1605, $dto->id);
        $this->assertSame('thunderstrike', $dto->slug);
    }
}
