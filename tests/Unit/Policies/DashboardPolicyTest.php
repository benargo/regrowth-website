<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\DashboardPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPolicyTest extends TestCase
{
    #[Test]
    public function it_grants_access_to_officers(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(true);

        $policy = new DashboardPolicy;

        $this->assertTrue($policy->access($user));
    }

    #[Test]
    public function it_denies_access_to_non_officers(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);

        $policy = new DashboardPolicy;

        $this->assertFalse($policy->access($user));
    }
}
