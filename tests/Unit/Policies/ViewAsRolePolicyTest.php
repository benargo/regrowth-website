<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\ViewAsRolePolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViewAsRolePolicyTest extends TestCase
{
    #[Test]
    public function it_allows_officers_to_view_as_role(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(true);

        $policy = new ViewAsRolePolicy;

        $this->assertTrue($policy->viewAsRole($user));
    }

    #[Test]
    public function it_denies_non_officers_from_viewing_as_role(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isOfficer')->willReturn(false);

        $policy = new ViewAsRolePolicy;

        $this->assertFalse($policy->viewAsRole($user));
    }
}
