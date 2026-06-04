<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Shared;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrefDataTest extends TestCase
{
    #[Test]
    public function it_casts_from_array(): void
    {
        $dto = HrefData::from(['href' => 'https://eu.api.blizzard.com/data/wow/x']);

        $this->assertSame('https://eu.api.blizzard.com/data/wow/x', $dto->href);
    }
}
