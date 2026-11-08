<?php

namespace Tests\Unit\Enums;

use App\Enums\Medal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('characters')]
class MedalTest extends TestCase
{
    #[Test]
    public function it_maps_positions_to_medals(): void
    {
        $this->assertSame(Medal::Gold, Medal::fromPosition(1));
        $this->assertSame(Medal::Silver, Medal::fromPosition(2));
        $this->assertSame(Medal::Bronze, Medal::fromPosition(3));
        $this->assertNull(Medal::fromPosition(4));
    }

    #[Test]
    public function it_exposes_the_embed_colours(): void
    {
        $this->assertSame(0xE3B341, Medal::Gold->color());
        $this->assertSame(0xC3CBD0, Medal::Silver->color());
        $this->assertSame(0xD08A4F, Medal::Bronze->color());
    }

    #[Test]
    public function it_exposes_the_ordinals(): void
    {
        $this->assertSame('first', Medal::Gold->ordinal());
        $this->assertSame('second', Medal::Silver->ordinal());
        $this->assertSame('third', Medal::Bronze->ordinal());
    }
}
