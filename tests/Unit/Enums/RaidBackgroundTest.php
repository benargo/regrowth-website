<?php

namespace Tests\Unit\Enums;

use App\Enums\RaidBackground;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaidBackgroundTest extends TestCase
{
    #[Test]
    public function all_enum_cases_have_string_values(): void
    {
        foreach (RaidBackground::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    #[Test]
    public function karazhan_has_correct_value(): void
    {
        $this->assertSame('bg-raid-karazhan', RaidBackground::KARAZHAN->value);
    }

    #[Test]
    public function gruul_magtheridon_has_correct_value(): void
    {
        $this->assertSame('bg-raid-gruul-magtheridon', RaidBackground::GRUUL_MAGTHERIDON->value);
    }

    #[Test]
    public function serpentshrine_cavern_has_correct_value(): void
    {
        $this->assertSame('bg-raid-serpentshrine-cavern', RaidBackground::SERPENTSHRINE_CAVERN->value);
    }

    #[Test]
    public function tempest_keep_has_correct_value(): void
    {
        $this->assertSame('bg-raid-tempest-keep', RaidBackground::TEMPEST_KEEP->value);
    }

    #[Test]
    public function ssc_tk_has_correct_value(): void
    {
        $this->assertSame('bg-ssctk', RaidBackground::SSC_TK->value);
    }
}
