<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\GameVersion;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameVersionTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        $this->assertCount(4, GameVersion::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame('Burning Crusade Classic (Anniversary)', GameVersion::Anniversary->value);
        $this->assertSame('Mists of Pandaria Classic (Progression)', GameVersion::Classic->value);
        $this->assertSame('World of Warcraft Classic (Era)', GameVersion::Era->value);
        $this->assertSame('World of Warcraft', GameVersion::Retail->value);
    }

    // ==================== namespaceComponent ====================

    #[Test]
    public function namespace_component_returns_correct_suffix_for_each_version(): void
    {
        $this->assertSame('-classicann', GameVersion::Anniversary->namespaceComponent());
        $this->assertSame('-classic', GameVersion::Classic->namespaceComponent());
        $this->assertSame('-classic1x', GameVersion::Era->namespaceComponent());
        $this->assertSame('', GameVersion::Retail->namespaceComponent());
    }

    #[Test]
    public function retail_namespace_component_is_an_empty_string(): void
    {
        $this->assertSame('', GameVersion::Retail->namespaceComponent());
    }
}
