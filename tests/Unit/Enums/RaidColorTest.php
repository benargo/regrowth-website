<?php

namespace Tests\Unit\Enums;

use App\Enums\RaidColor;
use PHPUnit\Framework\TestCase;

class RaidColorTest extends TestCase
{
    public function test_karazhan_returns_correct_color(): void
    {
        $this->assertSame(RaidColor::KARAZHAN, RaidColor::fromRaidId(1));
    }

    public function test_gruul_magtheridon_returns_correct_color_for_raid_id_2(): void
    {
        $this->assertSame(RaidColor::GRUUL_MAGTHERIDON, RaidColor::fromRaidId(2));
    }

    public function test_gruul_magtheridon_returns_correct_color_for_raid_id_3(): void
    {
        $this->assertSame(RaidColor::GRUUL_MAGTHERIDON, RaidColor::fromRaidId(3));
    }

    public function test_serpentshrine_cavern_returns_correct_color(): void
    {
        $this->assertSame(RaidColor::SERPENTSHRINE_CAVERN, RaidColor::fromRaidId(4));
    }

    public function test_tempest_keep_returns_correct_color(): void
    {
        $this->assertSame(RaidColor::TEMPEST_KEEP, RaidColor::fromRaidId(5));
    }

    public function test_unknown_raid_id_returns_default_color(): void
    {
        $this->assertSame(RaidColor::DEFAULT, RaidColor::fromRaidId(99));
    }

    public function test_iterable_raid_ids_uses_first_element(): void
    {
        $this->assertSame(RaidColor::KARAZHAN, RaidColor::fromRaidId([1, 2, 3]));
    }

    public function test_enum_values_are_valid_discord_color_integers(): void
    {
        foreach (RaidColor::cases() as $case) {
            $this->assertGreaterThanOrEqual(0, $case->value);
            $this->assertLessThanOrEqual(0xFFFFFF, $case->value);
        }
    }
}
