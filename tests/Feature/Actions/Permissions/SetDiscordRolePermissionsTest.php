<?php

namespace Tests\Feature\Actions\Permissions;

use App\Actions\Permissions\SetDiscordRolePermissions;
use App\Events\PermissionUpdated;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class SetDiscordRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_grants_every_listed_permission(): void
    {
        $role = DiscordRole::factory()->create();
        [$a, $b] = Permission::factory()->count(2)->create();

        SetDiscordRolePermissions::run($role, [$a->id, $b->id], true);

        $ids = $role->fresh()->permissions->pluck('id');
        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
    }

    #[Test]
    public function it_revokes_only_the_listed_permissions(): void
    {
        $role = DiscordRole::factory()->create();
        [$a, $b, $c] = Permission::factory()->count(3)->create();
        $role->permissions()->attach([$a->id, $b->id, $c->id]);

        SetDiscordRolePermissions::run($role, [$a->id, $b->id], false);

        $this->assertSame([$c->id], $role->fresh()->permissions->pluck('id')->all());
    }

    #[Test]
    public function it_does_not_duplicate_a_permission_already_granted(): void
    {
        $role = DiscordRole::factory()->create();
        $a = Permission::factory()->create();
        $role->permissions()->attach($a->id);

        SetDiscordRolePermissions::run($role, [$a->id], true);

        $this->assertSame(1, DB::table('discord_role_has_permissions')->where('permission_id', $a->id)->count());
    }

    #[Test]
    public function it_leaves_other_roles_alone(): void
    {
        [$role, $other] = DiscordRole::factory()->count(2)->create();
        $a = Permission::factory()->create();
        $other->permissions()->attach($a->id);
        $role->permissions()->attach($a->id);

        SetDiscordRolePermissions::run($role, [$a->id], false);

        $this->assertTrue($other->fresh()->permissions->contains($a));
        $this->assertFalse($role->fresh()->permissions->contains($a));
    }

    #[Test]
    public function it_dispatches_permission_updated_once_for_each_changed_permission(): void
    {
        $role = DiscordRole::factory()->create();
        [$a, $b] = Permission::factory()->count(2)->create();
        $role->permissions()->attach($a->id);
        Event::fake([PermissionUpdated::class]);

        SetDiscordRolePermissions::run($role, [$a->id, $b->id], true);

        Event::assertDispatchedTimes(PermissionUpdated::class, 1);
        Event::assertDispatched(PermissionUpdated::class, fn ($event) => $event->permission->is($b));
    }

    #[Test]
    public function it_dispatches_permission_updated_only_for_revoked_permissions(): void
    {
        $role = DiscordRole::factory()->create();
        [$a, $b] = Permission::factory()->count(2)->create();
        $role->permissions()->attach($a->id);
        Event::fake([PermissionUpdated::class]);

        SetDiscordRolePermissions::run($role, [$a->id, $b->id], false);

        Event::assertDispatchedTimes(PermissionUpdated::class, 1);
        Event::assertDispatched(PermissionUpdated::class, fn ($event) => $event->permission->is($a));
    }
}
