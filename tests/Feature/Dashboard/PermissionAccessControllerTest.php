<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class PermissionAccessControllerTest extends DashboardTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->officer()->admin()->create();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->patch(route('management.permissions.access.update'), [])->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)->patch(route('management.permissions.access.update'), [])->assertForbidden();
    }

    #[Test]
    public function it_grants_several_permissions_at_once(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        [$a, $b] = Permission::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->from(route('management.permissions.index'))
            ->patch(route('management.permissions.access.update'), [
                'discord_role_id' => $role->id,
                'permission_ids' => [$a->id, $b->id],
                'enabled' => true,
            ])
            ->assertRedirect(route('management.permissions.index'));

        $ids = $role->fresh()->permissions->pluck('id');
        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
    }

    #[Test]
    public function it_revokes_a_single_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        [$a, $b] = Permission::factory()->count(2)->create();
        $role->permissions()->attach([$a->id, $b->id]);

        $this->actingAs($this->admin)
            ->from(route('management.permissions.index'))
            ->patch(route('management.permissions.access.update'), [
                'discord_role_id' => $role->id,
                'permission_ids' => [$a->id],
                'enabled' => false,
            ])
            ->assertRedirect(route('management.permissions.index'));

        $ids = $role->fresh()->permissions->pluck('id');
        $this->assertFalse($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_role_that_does_not_grant_permissions(): void
    {
        $role = DiscordRole::factory()->create(['grants_permissions' => false]);
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.access.update'), [
                'discord_role_id' => $role->id,
                'permission_ids' => [$permission->id],
                'enabled' => true,
            ])
            ->assertInvalid(['discord_role_id' => 'The selected Discord role does not grant permissions.']);

        $this->assertSame(0, $role->permissions()->count());
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_empty_payload(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('management.permissions.access.update'), [])
            ->assertInvalid(['discord_role_id', 'permission_ids', 'enabled']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_permission(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.access.update'), [
                'discord_role_id' => $role->id,
                'permission_ids' => [99999],
                'enabled' => true,
            ])
            ->assertInvalid(['permission_ids.0']);
    }
}
