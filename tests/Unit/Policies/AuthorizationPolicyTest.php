<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\AuthorizationPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    private AuthorizationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new class extends AuthorizationPolicy {};
    }

    #[Test]
    public function it_returns_true_when_user_is_officer(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(true);

        $this->assertTrue($this->policy->userIsOfficer($user));
    }

    #[Test]
    public function it_returns_false_when_user_is_not_officer(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);

        $this->assertFalse($this->policy->userIsOfficer($user));
    }

    #[Test]
    public function it_returns_true_when_user_is_raider(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isRaider')->willReturn(true);

        $this->assertTrue($this->policy->userIsRaider($user));
    }

    #[Test]
    public function it_returns_false_when_user_is_not_raider(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isRaider')->willReturn(false);

        $this->assertFalse($this->policy->userIsRaider($user));
    }

    #[Test]
    public function it_returns_true_when_user_is_member(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isMember')->willReturn(true);

        $this->assertTrue($this->policy->userIsMember($user));
    }

    #[Test]
    public function it_returns_false_when_user_is_not_member(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isMember')->willReturn(false);

        $this->assertFalse($this->policy->userIsMember($user));
    }

    #[Test]
    public function it_returns_true_when_user_is_loot_councillor(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isLootCouncillor')->willReturn(true);

        $this->assertTrue($this->policy->userIsLootCouncillor($user));
    }

    #[Test]
    public function it_returns_false_when_user_is_not_loot_councillor(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isLootCouncillor')->willReturn(false);

        $this->assertFalse($this->policy->userIsLootCouncillor($user));
    }

    #[Test]
    public function member_or_above_returns_true_for_officer(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(true);
        $user->method('isRaider')->willReturn(false);
        $user->method('isMember')->willReturn(false);

        $this->assertTrue($this->policy->userIsMemberOrAbove($user));
    }

    #[Test]
    public function member_or_above_returns_true_for_raider(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);
        $user->method('isRaider')->willReturn(true);
        $user->method('isMember')->willReturn(false);

        $this->assertTrue($this->policy->userIsMemberOrAbove($user));
    }

    #[Test]
    public function member_or_above_returns_true_for_member(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);
        $user->method('isRaider')->willReturn(false);
        $user->method('isMember')->willReturn(true);

        $this->assertTrue($this->policy->userIsMemberOrAbove($user));
    }

    #[Test]
    public function member_or_above_returns_false_when_none(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);
        $user->method('isRaider')->willReturn(false);
        $user->method('isMember')->willReturn(false);

        $this->assertFalse($this->policy->userIsMemberOrAbove($user));
    }
}
