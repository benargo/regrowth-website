<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Shared;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

class LinkDataTest extends TestCase
{
    #[Test]
    public function it_casts_full_link_with_id_and_name(): void
    {
        $dto = LinkData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/1?namespace=static-eu'],
            'name' => 'Warrior',
            'id' => 1,
        ]);

        $this->assertInstanceOf(HrefData::class, $dto->key);
        $this->assertSame('https://eu.api.blizzard.com/data/wow/playable-class/1?namespace=static-eu', (string) $dto->key->href);
        $this->assertSame('Warrior', $dto->name);
        $this->assertSame(1, $dto->id);
    }

    #[Test]
    public function it_treats_missing_name_and_id_as_optional(): void
    {
        $dto = LinkData::from([
            'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/x'],
        ]);

        $this->assertInstanceOf(Optional::class, $dto->name);
        $this->assertInstanceOf(Optional::class, $dto->id);
    }
}
