<?php

namespace Tests\Unit\Enums;

use App\Enums\Gender;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GenderTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        $this->assertCount(2, Gender::cases());
    }

    #[Test]
    public function all_cases_have_string_values(): void
    {
        foreach (Gender::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }

    #[Test]
    public function each_case_has_correct_value(): void
    {
        $this->assertSame('Female', Gender::FEMALE->value);
        $this->assertSame('Male', Gender::MALE->value);
    }

    // ==================== id ====================

    #[Test]
    public function male_id_returns_zero(): void
    {
        $this->assertSame(0, Gender::MALE->id());
    }

    #[Test]
    public function female_id_returns_one(): void
    {
        $this->assertSame(1, Gender::FEMALE->id());
    }

    // ==================== fromId ====================

    #[Test]
    public function from_id_returns_male_for_zero(): void
    {
        $this->assertSame(Gender::MALE, Gender::fromId(0));
    }

    #[Test]
    public function from_id_returns_female_for_one(): void
    {
        $this->assertSame(Gender::FEMALE, Gender::fromId(1));
    }
}
