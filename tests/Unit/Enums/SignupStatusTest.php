<?php

namespace Tests\Unit\Enums;

use App\Enums\SignupStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class SignupStatusTest extends TestCase
{
    #[Test]
    public function it_has_three_cases_with_correct_backing_values(): void
    {
        $this->assertSame('confirmed', SignupStatus::Confirmed->value);
        $this->assertSame('unconfirmed', SignupStatus::Unconfirmed->value);
        $this->assertSame('cancelled', SignupStatus::Cancelled->value);
    }

    #[Test]
    public function it_can_be_created_from_any_valid_string(): void
    {
        $this->assertSame(SignupStatus::Confirmed, SignupStatus::from('confirmed'));
        $this->assertSame(SignupStatus::Unconfirmed, SignupStatus::from('unconfirmed'));
        $this->assertSame(SignupStatus::Cancelled, SignupStatus::from('cancelled'));
    }

    #[Test]
    public function is_confirmed_returns_true_only_for_confirmed_case(): void
    {
        $this->assertTrue(SignupStatus::Confirmed->isConfirmed());
        $this->assertFalse(SignupStatus::Unconfirmed->isConfirmed());
        $this->assertFalse(SignupStatus::Cancelled->isConfirmed());
    }

    #[Test]
    public function default_constant_is_unconfirmed(): void
    {
        $this->assertSame(SignupStatus::Unconfirmed, SignupStatus::DEFAULT);
    }
}
