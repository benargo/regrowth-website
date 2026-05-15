<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\SpellPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SpellPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SpellPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SpellPolicy;
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
    public function it_allows_create_with_edit_datasets_permission(): void
    {
        $user = $this->userWithPermission('edit-datasets');

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function it_denies_create_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->create($user));
    }

    #[Test]
    public function it_denies_create_with_unrelated_permission(): void
    {
        $user = $this->userWithPermission('manage-raid-plans');

        $this->assertFalse($this->policy->create($user));
    }
}
