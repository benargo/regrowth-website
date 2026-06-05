<?php

namespace Tests\Unit\Enums;

use App\Enums\CharacterRole;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterRoleTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        $this->assertCount(3, CharacterRole::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame('Tank', CharacterRole::tank->value);
        $this->assertSame('Healer', CharacterRole::healer->value);
        $this->assertSame('DPS', CharacterRole::damage->value);
    }

    // ==================== icon ====================

    #[Test]
    public function tank_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_tank.webp'), CharacterRole::tank->icon());
    }

    #[Test]
    public function healer_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_healer.webp'), CharacterRole::healer->icon());
    }

    #[Test]
    public function damage_icon_returns_correct_url(): void
    {
        $this->assertSame(asset('images/role_damage.webp'), CharacterRole::damage->icon());
    }
}
