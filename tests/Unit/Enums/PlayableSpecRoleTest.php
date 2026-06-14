<?php

namespace Tests\Unit\Enums;

use App\Enums\PlayableSpecRole;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class PlayableSpecRoleTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        $this->assertCount(3, PlayableSpecRole::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame('Tank', PlayableSpecRole::tank->value);
        $this->assertSame('Healer', PlayableSpecRole::healer->value);
        $this->assertSame('DPS', PlayableSpecRole::damage->value);
    }

    // ==================== icon ====================

    #[Test]
    public function tank_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_tank.webp'), PlayableSpecRole::tank->icon());
    }

    #[Test]
    public function healer_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_healer.webp'), PlayableSpecRole::healer->icon());
    }

    #[Test]
    public function damage_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_damage.webp'), PlayableSpecRole::damage->icon());
    }
}
