<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class PermissionControllerTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        $response = $this->get(route('dashboard.permissions.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.permissions.index'));

        $response->assertRedirect(route('dashboard.permissions.group.show', ['group' => 'loot-bias-tool']));
    }

    #[Test]
    public function show_group_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function show_group_forbids_non_officer_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertForbidden();
    }

    #[Test]
    public function show_group_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertOk();
    }

    #[Test]
    public function show_group_returns_discord_roles_groups_and_permissions(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.permissions.group.show', ['group' => 'loot-bias-tool']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePermissions')
            ->has('discordRoles')
            ->has('groups')
            ->has('permissions')
        );
    }

    #[Test]
    public function show_group_returns_404_for_unknown_group(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.permissions.group.show', ['group' => 'nonexistent-group']));

        $response->assertNotFound();
    }

    #[Test]
    public function toggle_requires_authentication(): void
    {
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->patch(route('dashboard.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function toggle_forbids_non_officer_users(): void
    {
        $user = User::factory()->member()->create();
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->patch(route('dashboard.permissions.permission.update', [
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
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertFalse($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
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
        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('comment-on-loot-items');
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertTrue($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
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

    #[Test]
    public function toggle_validates_discord_role_id_is_required(): void
    {
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
    }

    #[Test]
    public function toggle_validates_discord_role_id_must_exist(): void
    {
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
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
        $role = DiscordRole::factory()->create();

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
            'group' => 'loot-bias-tool',
            'permission' => 9999,
        ]), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function toggle_validates_enabled_is_required(): void
    {
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors(['enabled']);
    }

    #[Test]
    public function toggle_forbids_non_admin_from_modifying_their_highest_role(): void
    {
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5],
        );
        $officerRole->update(['is_visible' => true]);

        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $officerRole->id,
            'enabled' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $officerRole->id,
            'permission_id' => $permission->id,
        ]);
    }

    #[Test]
    public function toggle_allows_admin_to_modify_their_highest_role(): void
    {
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5],
        );
        $officerRole->update(['is_visible' => true]);

        $user = User::factory()->officer()->admin()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->patch(route('dashboard.permissions.permission.update', [
            'group' => $permission->group,
            'permission' => $permission->id,
        ]), [
            'discord_role_id' => $officerRole->id,
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $officerRole->load('permissions');
        $this->assertTrue($officerRole->hasPermissionTo('comment-on-loot-items'));
    }

    #[Test]
    public function toggle_does_not_affect_other_roles(): void
    {
        $role1 = DiscordRole::factory()->create();
        $role2 = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $role2->givePermissionTo('comment-on-loot-items');

        $this->actingAs($this->officer)->patch(route('dashboard.permissions.permission.update', [
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
