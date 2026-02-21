<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.permissions.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    public function test_index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    public function test_index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertForbidden();
    }

    public function test_index_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertOk();
    }

    public function test_index_returns_discord_roles_and_permissions(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePermissions')
            ->has('discordRoles')
            ->has('permissions')
        );
    }

    public function test_toggle_requires_authentication(): void
    {
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_toggle_forbids_non_officer_users(): void
    {
        $user = User::factory()->member()->create();
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_can_enable_a_permission(): void
    {
        $user = User::factory()->officer()->create();
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertFalse($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $role->load('permissions');
        $this->assertTrue($role->hasPermissionTo('comment-on-loot-items'));
    }

    public function test_toggle_can_disable_a_permission(): void
    {
        $user = User::factory()->officer()->create();
        $role = DiscordRole::factory()->create();
        $role->givePermissionTo('comment-on-loot-items');
        $permission = Permission::findByName('comment-on-loot-items');

        $this->assertTrue($role->hasPermissionTo('comment-on-loot-items'));

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
            'enabled' => false,
        ]);

        $response->assertRedirect();
        $role->load('permissions');
        $this->assertFalse($role->hasPermissionTo('comment-on-loot-items'));
    }

    public function test_toggle_validates_discord_role_id_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
    }

    public function test_toggle_validates_discord_role_id_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => 'nonexistent',
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['discord_role_id']);
    }

    public function test_toggle_validates_permission_id_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $role = DiscordRole::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['permission_id']);
    }

    public function test_toggle_validates_permission_id_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $role = DiscordRole::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => 9999,
            'enabled' => true,
        ]);

        $response->assertSessionHasErrors(['permission_id']);
    }

    public function test_toggle_validates_enabled_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $role = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $response->assertSessionHasErrors(['enabled']);
    }

    public function test_toggle_forbids_non_admin_from_modifying_their_highest_role(): void
    {
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 1],
        );
        $officerRole->update(['is_visible' => true]);

        $user = User::factory()->officer()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $officerRole->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $officerRole->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_toggle_allows_admin_to_modify_their_highest_role(): void
    {
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 1],
        );
        $officerRole->update(['is_visible' => true]);

        $user = User::factory()->officer()->admin()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $response = $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $officerRole->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $officerRole->load('permissions');
        $this->assertTrue($officerRole->hasPermissionTo('comment-on-loot-items'));
    }

    public function test_toggle_does_not_affect_other_roles(): void
    {
        $user = User::factory()->officer()->create();
        $role1 = DiscordRole::factory()->create();
        $role2 = DiscordRole::factory()->create();
        $permission = Permission::findByName('comment-on-loot-items');

        $role2->givePermissionTo('comment-on-loot-items');

        $this->actingAs($user)->post(route('dashboard.permissions.toggle'), [
            'discord_role_id' => $role1->id,
            'permission_id' => $permission->id,
            'enabled' => true,
        ]);

        $role1->load('permissions');
        $role2->load('permissions');

        $this->assertTrue($role1->hasPermissionTo('comment-on-loot-items'));
        $this->assertTrue($role2->hasPermissionTo('comment-on-loot-items'));
    }
}
