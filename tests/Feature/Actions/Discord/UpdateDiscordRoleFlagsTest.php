<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\UpdateDiscordRoleFlags;
use App\Listeners\FlushPermissionsCache;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class UpdateDiscordRoleFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function permission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    #[Test]
    public function it_turns_grants_permissions_on(): void
    {
        $role = DiscordRole::factory()->create(['grants_permissions' => false, 'is_visible' => false]);

        $result = UpdateDiscordRoleFlags::run($role, ['grants_permissions' => true]);

        $this->assertTrue($result->fresh()->grants_permissions);
        $this->assertFalse($result->fresh()->is_visible);
    }

    #[Test]
    public function it_turns_is_visible_on(): void
    {
        $role = DiscordRole::factory()->create(['grants_permissions' => false, 'is_visible' => false]);

        $result = UpdateDiscordRoleFlags::run($role, ['is_visible' => true]);

        $this->assertTrue($result->fresh()->is_visible);
        $this->assertFalse($result->fresh()->grants_permissions);
    }

    #[Test]
    public function turning_grants_permissions_off_detaches_every_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $role->givePermissionTo($this->permission('view-handbook'));
        $role->givePermissionTo($this->permission('comment-on-loot-items'));

        UpdateDiscordRoleFlags::run($role, ['grants_permissions' => false]);

        $this->assertSame(0, $role->permissions()->count());
    }

    #[Test]
    public function toggling_is_visible_keeps_permissions(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $role->givePermissionTo($this->permission('view-handbook'));

        UpdateDiscordRoleFlags::run($role, ['is_visible' => false]);

        $this->assertSame(1, $role->permissions()->count());
    }

    #[Test]
    public function turning_grants_permissions_on_keeps_permissions_untouched(): void
    {
        $role = DiscordRole::factory()->create(['grants_permissions' => false]);
        $permission = $this->permission('view-handbook');
        DB::table('discord_role_has_permissions')->insert([
            'discord_role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        UpdateDiscordRoleFlags::run($role, ['grants_permissions' => true]);

        $this->assertSame(1, $role->permissions()->count());
    }

    #[Test]
    public function it_flushes_the_permissions_cache(): void
    {
        $this->app->forgetInstance(FlushPermissionsCache::class);
        $role = DiscordRole::factory()->create(['grants_permissions' => false]);
        Cache::tags(['permissions'])->put('probe', true);

        UpdateDiscordRoleFlags::run($role, ['grants_permissions' => true]);

        $this->assertNull(Cache::tags(['permissions'])->get('probe'));
    }
}
