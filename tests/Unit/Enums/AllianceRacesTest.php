<?php

namespace Tests\Unit\Enums;

use App\Enums\AllianceRaces;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AllianceRacesTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, AllianceRaces::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame(1, AllianceRaces::Human->value);
        $this->assertSame(3, AllianceRaces::Dwarf->value);
        $this->assertSame(4, AllianceRaces::NightElf->value);
        $this->assertSame(7, AllianceRaces::Gnome->value);
        $this->assertSame(11, AllianceRaces::Draenei->value);
    }

    // ==================== fromId ====================

    #[Test]
    public function from_id_returns_correct_case_for_valid_id(): void
    {
        $this->assertSame(AllianceRaces::Human, AllianceRaces::fromId(1));
        $this->assertSame(AllianceRaces::Dwarf, AllianceRaces::fromId(3));
        $this->assertSame(AllianceRaces::NightElf, AllianceRaces::fromId(4));
        $this->assertSame(AllianceRaces::Gnome, AllianceRaces::fromId(7));
        $this->assertSame(AllianceRaces::Draenei, AllianceRaces::fromId(11));
    }

    #[Test]
    public function from_id_returns_null_for_invalid_id(): void
    {
        $this->assertNull(AllianceRaces::fromId(0));
        $this->assertNull(AllianceRaces::fromId(2));
        $this->assertNull(AllianceRaces::fromId(999));
    }

    // ==================== ids ====================

    #[Test]
    public function ids_returns_all_case_values(): void
    {
        $this->assertSame([1, 3, 4, 7, 11], AllianceRaces::ids());
    }
}
