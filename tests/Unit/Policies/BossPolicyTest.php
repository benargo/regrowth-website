<?php

namespace Tests\Unit\Policies;

use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\BossPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BossPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BossPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new BossPolicy;
    }

    private function userWithPermission(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }

    private function userWithoutPermission(): User
    {
        $user = User::factory()->create();
        $user->load('discordRoles.permissions');

        return $user;
    }

    #[Test]
    public function it_allows_update_with_manage_boss_strategies_permission(): void
    {
        $user = $this->userWithPermission('manage-boss-strategies');
        $boss = Boss::factory()->create();

        $this->assertTrue($this->policy->update($user, $boss));
    }

    #[Test]
    public function it_denies_update_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $boss = Boss::factory()->create();

        $this->assertFalse($this->policy->update($user, $boss));
    }

    #[Test]
    public function it_denies_update_with_unrelated_permission(): void
    {
        $user = $this->userWithPermission('view-raid-plans');
        $boss = Boss::factory()->create();

        $this->assertFalse($this->policy->update($user, $boss));
    }
}
