<?php

namespace Tests\Unit\Enums;

use App\Enums\RaidBackground;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidBackgroundTest extends TestCase
{
    #[Test]
    public function all_enum_cases_have_string_values(): void
    {
        foreach (RaidBackground::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    // ==================== case values ====================

    #[Test]
    public function karazhan_has_correct_value(): void
    {
        $this->assertSame('bg-raid-karazhan', RaidBackground::Karazhan->value);
    }

    #[Test]
    public function gruul_magtheridon_has_correct_value(): void
    {
        $this->assertSame('bg-raid-gruul-magtheridon', RaidBackground::GruulAndMagtheridon->value);
    }

    #[Test]
    public function serpentshrine_cavern_has_correct_value(): void
    {
        $this->assertSame('bg-raid-serpentshrine-cavern', RaidBackground::SerpentshrineCavern->value);
    }

    #[Test]
    public function tempest_keep_has_correct_value(): void
    {
        $this->assertSame('bg-raid-tempest-keep', RaidBackground::TempestKeep->value);
    }

    #[Test]
    public function ssc_tk_has_correct_value(): void
    {
        $this->assertSame('bg-ssctk', RaidBackground::SerpentshrineCavernAndTempestKeep->value);
    }

    #[Test]
    public function gruul_has_correct_value(): void
    {
        $this->assertSame('bg-raid-gruul', RaidBackground::Gruul->value);
    }

    #[Test]
    public function magtheridon_has_correct_value(): void
    {
        $this->assertSame('bg-raid-magtheridon', RaidBackground::Magtheridon->value);
    }

    #[Test]
    public function hyjal_summit_has_correct_value(): void
    {
        $this->assertSame('bg-raid-hyjal-summit', RaidBackground::HyjalSummit->value);
    }

    #[Test]
    public function black_temple_has_correct_value(): void
    {
        $this->assertSame('bg-raid-black-temple', RaidBackground::BlackTemple->value);
    }

    #[Test]
    public function zul_aman_has_correct_value(): void
    {
        $this->assertSame('bg-raid-zulaman', RaidBackground::ZulAman->value);
    }

    #[Test]
    public function sunwell_plateau_has_correct_value(): void
    {
        $this->assertSame('bg-raid-sunwell-plateau', RaidBackground::SunwellPlateau->value);
    }
}
