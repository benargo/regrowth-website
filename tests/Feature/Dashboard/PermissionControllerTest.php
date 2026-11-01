<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class PermissionControllerTest extends DashboardTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->officer()->admin()->create();

        // Clear cached permissions to ensure the tests reflect the current state of permissions.
        Cache::tags(['permissions'])->flush();

        Permission::firstOrCreate(
            ['name' => 'comment-on-loot-items', 'guard_name' => 'web'],
            ['group' => 'loot-bias-tool'],
        );
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('management.permissions.index'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('management.permissions.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.permissions.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('management.permissions.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_admin_users(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.permissions.index'));

        $response->assertRedirect(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_officers_who_are_not_admins(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.permissions.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function show_group_forbids_officers_who_are_not_admins(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertForbidden();
    }

    #[Test]
    public function show_group_lists_only_roles_that_grant_permissions(): void
    {
        $granting = DiscordRole::factory()->visible()->grantsPermissions()->create();
        DiscordRole::factory()->visible()->create();
        Cache::tags(['db', 'discord', 'permissions'])->flush();

        $response = $this->actingAs($this->admin)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertInertia(fn ($page) => $page
            ->where('discordRoles', fn ($roles) => collect($roles)->pluck('id')->sort()->values()->all() === collect(['829021769448816691', $granting->id])->sort()->values()->all())
        );
    }

    #[Group('authorization')]
    #[Test]
    public function toggle_forbids_officers_who_are_not_admins(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->officer)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertForbidden();
    }

    #[Group('validation')]
    #[Test]
    public function toggle_rejects_a_role_that_does_not_grant_permissions(): void
    {
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permission->id]);
    }

    // ==================== show group ====================

    #[Test]
    public function show_group_requires_authentication(): void
    {
        $response = $this->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function show_group_forbids_non_officer_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertForbidden();
    }

    #[Test]
    public function show_group_allows_admin_users(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertOk();
    }

    #[Test]
    public function show_group_returns_discord_roles_groups_and_permissions(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Permissions/Show')
            ->has('discordRoles')
            ->has('groups')
            ->has('permissions')
        );
    }

    #[Test]
    public function show_group_saves_group_to_session(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertSessionHas('dashboard.permissions.group', 'loot-bias-tool');
    }

    #[Test]
    public function show_group_returns_404_for_unknown_group(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.permissions.group.show', ['group' => 'nonexistent-group']));

        $response->assertNotFound();
    }

    #[Test]
    public function index_redirects_to_session_group_when_present(): void
    {
        Permission::firstOrCreate(
            ['name' => 'manage-roster', 'guard_name' => 'web'],
            ['group' => 'roster'],
        );

        $response = $this->actingAs($this->admin)
            ->withSession(['dashboard.permissions.group' => 'roster'])
            ->get(route('management.permissions.index'));

        $response->assertRedirect(route('management.permissions.group.show', ['group' => 'roster']));
    }

    // ==================== toggle ====================

    #[Test]
    public function toggle_requires_authentication(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function toggle_forbids_non_officer_users(): void
    {
        $user = User::factory()->member()->create();
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function toggle_can_enable_a_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertFalse($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $role->load('permissions');
        $this->assertTrue($role->hasPermissionTo('comment-on-loot-items'));
    }

    #[Test]
    public function toggle_can_disable_a_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $role->givePermissionTo('comment-on-loot-items');
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertTrue($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => false,
        ]);

        $response->assertRedirect();
        $role->load('permissions');
        $this->assertFalse($role->hasPermissionTo('comment-on-loot-items'));
    }

    // ==================== toggle — validation ====================

    #[Group('validation')]
    #[Test]
    public function toggle_validates_discord_role_id_is_required(): void
    {
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
    }

    #[Group('validation')]
    #[Test]
    public function toggle_validates_discord_role_id_must_exist(): void
    {
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => 'nonexistent',
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
    }

    #[Test]
    public function toggle_returns_404_for_nonexistent_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => 'loot-bias-tool',
            'permission' => 9999,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertNotFound();
    }

    #[Group('validation')]
    #[Test]
    public function toggle_validates_enabled_is_required(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors(['enabled']);
    }

    // ==================== toggle — side effects ====================

    #[Test]
    public function toggle_does_not_affect_other_roles(): void
    {
        $role1 = DiscordRole::factory()->grantsPermissions()->create();
        $role2 = DiscordRole::factory()->grantsPermissions()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $role2->givePermissionTo('comment-on-loot-items');

        $this->actingAs($this->admin)->patch(route('management.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role1->id,
            'enabled' => true,
        ]);

        $role1->load('permissions');
        $role2->load('permissions');

        $this->assertTrue($role1->hasPermissionTo('comment-on-loot-items'));
        $this->assertTrue($role2->hasPermissionTo('comment-on-loot-items'));
    }
}
