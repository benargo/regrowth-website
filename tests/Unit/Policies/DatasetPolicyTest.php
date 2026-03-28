<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\User;
use App\Policies\DatasetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatasetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DatasetPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DatasetPolicy;
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
    public function it_allows_view_any_with_edit_datasets_permission(): void
    {
        $user = $this->userWithPermission('edit-datasets');

        $this->assertTrue($this->policy->viewAny($user));
    }

    #[Test]
    public function it_denies_view_any_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function it_allows_view_with_permission(): void
    {
        $user = $this->userWithPermission('edit-datasets');
        $model = GuildRank::factory()->create();

        $this->assertTrue($this->policy->view($user, $model));
    }

    #[Test]
    public function it_denies_view_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $model = GuildRank::factory()->create();

        $this->assertFalse($this->policy->view($user, $model));
    }

    #[Test]
    public function it_allows_create_with_permission(): void
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
    public function it_allows_update_with_permission(): void
    {
        $user = $this->userWithPermission('edit-datasets');
        $model = GuildRank::factory()->create();

        $this->assertTrue($this->policy->update($user, $model));
    }

    #[Test]
    public function it_allows_delete_with_permission(): void
    {
        $user = $this->userWithPermission('edit-datasets');
        $model = GuildRank::factory()->create();

        $this->assertTrue($this->policy->delete($user, $model));
    }

    #[Test]
    public function it_allows_restore_for_admins(): void
    {
        $user = User::factory()->admin()->create();
        $model = GuildRank::factory()->create();

        $this->assertTrue($this->policy->restore($user, $model));
    }

    #[Test]
    public function it_denies_restore_for_non_admins(): void
    {
        $user = User::factory()->create();
        $model = GuildRank::factory()->create();

        $this->assertFalse($this->policy->restore($user, $model));
    }

    #[Test]
    public function it_allows_force_delete_for_admins(): void
    {
        $user = User::factory()->admin()->create();
        $model = GuildRank::factory()->create();

        $this->assertTrue($this->policy->forceDelete($user, $model));
    }

    #[Test]
    public function it_denies_force_delete_for_non_admins(): void
    {
        $user = User::factory()->create();
        $model = GuildRank::factory()->create();

        $this->assertFalse($this->policy->forceDelete($user, $model));
    }
}
