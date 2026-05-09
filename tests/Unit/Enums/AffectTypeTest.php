<?php

namespace Tests\Unit\Enums;

use App\Enums\AffectType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AffectTypeTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, AffectType::cases());
    }

    #[Test]
    public function all_cases_have_string_values(): void
    {
        foreach (AffectType::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    #[Test]
    public function each_case_has_correct_value(): void
    {
        $this->assertSame('Curse', AffectType::Curse->value);
        $this->assertSame('Disease', AffectType::Disease->value);
        $this->assertSame('Magic', AffectType::Magic->value);
        $this->assertSame('Poison', AffectType::Poison->value);
        $this->assertSame('Physical', AffectType::Physical->value);
    }

    // ==================== color ====================

    #[Test]
    public function curse_has_correct_color(): void
    {
        $this->assertSame('bg-affect-curse', AffectType::Curse->color());
    }

    #[Test]
    public function disease_has_correct_color(): void
    {
        $this->assertSame('bg-affect-disease', AffectType::Disease->color());
    }

    #[Test]
    public function magic_has_correct_color(): void
    {
        $this->assertSame('bg-affect-magic', AffectType::Magic->color());
    }

    #[Test]
    public function poison_has_correct_color(): void
    {
        $this->assertSame('bg-affect-poison', AffectType::Poison->color());
    }

    #[Test]
    public function physical_has_correct_color(): void
    {
        $this->assertSame('bg-affect-physical', AffectType::Physical->color());
    }

    #[Test]
    public function all_cases_return_string_color(): void
    {
        foreach (AffectType::cases() as $case) {
            $this->assertIsString($case->color());
            $this->assertStringStartsWith('bg-affect-', $case->color());
        }
    }
}
