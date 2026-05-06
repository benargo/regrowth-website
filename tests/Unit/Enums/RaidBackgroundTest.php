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
    public function default_has_correct_value(): void
    {
        $this->assertSame('bg-ssctk', RaidBackground::DEFAULT->value);
    }

    #[Test]
    public function from_raid_id_returns_karazhan_for_id_1(): void
    {
        $result = RaidBackground::fromRaidId(1);

        $this->assertSame(RaidBackground::KARAZHAN, $result);
    }

    #[Test]
    public function from_raid_id_returns_gruul_magtheridon_for_id_2(): void
    {
        $result = RaidBackground::fromRaidId(2);

        $this->assertSame(RaidBackground::GRUUL_MAGTHERIDON, $result);
    }

    #[Test]
    public function from_raid_id_returns_gruul_magtheridon_for_id_3(): void
    {
        $result = RaidBackground::fromRaidId(3);

        $this->assertSame(RaidBackground::GRUUL_MAGTHERIDON, $result);
    }

    #[Test]
    public function from_raid_id_returns_serpentshrine_cavern_for_id_4(): void
    {
        $result = RaidBackground::fromRaidId(4);

        $this->assertSame(RaidBackground::SERPENTSHRINE_CAVERN, $result);
    }

    #[Test]
    public function from_raid_id_returns_tempest_keep_for_id_5(): void
    {
        $result = RaidBackground::fromRaidId(5);

        $this->assertSame(RaidBackground::TEMPEST_KEEP, $result);
    }

    #[Test]
    public function from_raid_id_returns_default_for_unknown_id(): void
    {
        $result = RaidBackground::fromRaidId(999);

        $this->assertSame(RaidBackground::DEFAULT, $result);
    }

    #[Test]
    public function from_raid_id_returns_default_for_zero(): void
    {
        $result = RaidBackground::fromRaidId(0);

        $this->assertSame(RaidBackground::DEFAULT, $result);
    }

    #[Test]
    public function from_raid_id_accepts_array_and_uses_first_element(): void
    {
        $result = RaidBackground::fromRaidId([2, 3, 4]);

        $this->assertSame(RaidBackground::GRUUL_MAGTHERIDON, $result);
    }

    #[Test]
    public function from_raid_id_accepts_single_element_array(): void
    {
        $result = RaidBackground::fromRaidId([5]);

        $this->assertSame(RaidBackground::TEMPEST_KEEP, $result);
    }

    #[Test]
    public function from_raid_id_returns_default_for_empty_array(): void
    {
        $result = RaidBackground::fromRaidId([]);

        $this->assertSame(RaidBackground::DEFAULT, $result);
    }
}
