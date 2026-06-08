<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Shared;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrefDataTest extends TestCase
{
    #[Test]
    public function it_casts_the_href_to_a_uri(): void
    {
        $dto = HrefData::from(['href' => 'https://eu.api.blizzard.com/data/wow/x']);

        $this->assertInstanceOf(Uri::class, $dto->href);
        $this->assertSame('https://eu.api.blizzard.com/data/wow/x', (string) $dto->href);
    }

    #[Test]
    public function it_serialises_the_href_back_to_a_string(): void
    {
        $dto = HrefData::from(['href' => 'https://eu.api.blizzard.com/data/wow/x']);

        $this->assertSame('https://eu.api.blizzard.com/data/wow/x', $dto->toArray()['href']);
    }
}
