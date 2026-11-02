<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class PermissionControllerTest extends DashboardTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->officer()->admin()->create();
    }

    /**
     * @return array{nonGranting: DiscordRole, high: DiscordRole, low: DiscordRole}
     */
    private function createRoles(): array
    {
        return [
            'nonGranting' => DiscordRole::factory()->create(['position' => 900, 'grants_permissions' => false]),
            'high' => DiscordRole::factory()->grantsPermissions()->create(['position' => 500]),
            'low' => DiscordRole::factory()->grantsPermissions()->create(['position' => 300]),
        ];
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->get(route('management.permissions.index'))->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)->get(route('management.permissions.index'))->assertForbidden();
    }

    #[Test]
    public function index_lists_only_roles_that_grant_permissions_highest_first(): void
    {
        $roles = $this->createRoles();

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/Permissions/Access')
                ->where('discordRoles.data.0.id', $roles['high']->id)
                ->where('discordRoles.data.1.id', $roles['low']->id)
                ->where('discordRoles.data.2.id', '829021769448816691')
                ->has('discordRoles.data', 3)
            );
    }

    #[Test]
    public function index_selects_the_requested_role(): void
    {
        $roles = $this->createRoles();

        $this->actingAs($this->admin)->get(route('management.permissions.index', ['role' => $roles['low']->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedRoleId', $roles['low']->id));
    }

    #[TestWith(['nope'])]
    #[TestWith(['non-granting'])]
    #[Test]
    public function index_falls_back_to_the_highest_granting_role(string $requested): void
    {
        $roles = $this->createRoles();

        if ($requested === 'non-granting') {
            $requested = $roles['nonGranting']->id;
        }

        $this->actingAs($this->admin)->get(route('management.permissions.index', ['role' => $requested]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selectedRoleId', $roles['high']->id));
    }

    #[Test]
    public function index_selects_no_role_when_none_grant_permissions(): void
    {
        DiscordRole::query()->update(['grants_permissions' => false]);

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedRoleId', null)
                ->has('discordRoles.data', 0)
            );
    }

    #[Test]
    public function index_lists_groups_in_order_with_their_permissions_and_holders(): void
    {
        $second = PermissionGroup::factory()->create();
        $first = PermissionGroup::factory()->create();
        $second->update(['sort_order' => 2]);
        $first->update(['sort_order' => 1]);
        $held = Permission::factory()->create(['permission_group_id' => $first->id]);
        Permission::factory()->create(['permission_group_id' => $second->id]);
        DiscordRole::find('829021769448816691')->permissions()->attach($held);

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissionGroups.data.0.id', $first->id)
                ->where('permissionGroups.data.1.id', $second->id)
                ->where('permissionGroups.data.0.permissions.0.discord_role_ids', ['829021769448816691'])
            );
    }

    #[Test]
    public function index_orders_permissions_by_label_then_key(): void
    {
        $group = PermissionGroup::factory()->create();
        Permission::factory()->create(['name' => 'z-key', 'label' => 'Alpha', 'permission_group_id' => $group->id]);
        Permission::factory()->create(['name' => 'm-key', 'label' => null, 'permission_group_id' => $group->id]);

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissionGroups.data.0.permissions.0.name', 'z-key')
                ->where('permissionGroups.data.0.permissions.1.name', 'm-key')
            );
    }

    #[Test]
    public function index_omits_empty_groups(): void
    {
        PermissionGroup::factory()->create();

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('permissionGroups.data', 0));
    }

    #[Test]
    public function index_lists_ungrouped_permissions_separately(): void
    {
        $group = PermissionGroup::factory()->create();
        $grouped = Permission::factory()->create(['permission_group_id' => $group->id]);

        $this->actingAs($this->admin)->get(route('management.permissions.index'))
            ->assertInertia(function (Assert $page) use ($grouped) {
                $names = collect($page->toArray()['props']['ungroupedPermissions']['data'])->pluck('name');
                $this->assertTrue($names->contains('view-officer-dashboard'));
                $this->assertFalse($names->contains($grouped->name));
            });
    }

    // ==================== manage ====================

    #[Test]
    public function manage_requires_authentication(): void
    {
        $this->get(route('management.permissions.manage'))->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function manage_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)->get(route('management.permissions.manage'))->assertForbidden();
    }

    #[Test]
    public function manage_lists_every_group_including_empty_ones_with_role_counts(): void
    {
        $group = PermissionGroup::factory()->create();
        $empty = PermissionGroup::factory()->create();
        $permission = Permission::factory()->create(['permission_group_id' => $group->id]);
        DiscordRole::find('829021769448816691')->permissions()->attach($permission);

        $this->actingAs($this->admin)->get(route('management.permissions.manage'))
            ->assertInertia(function (Assert $page) use ($group, $empty, $permission) {
                $page->component('Manage/Permissions/Manage');
                $groups = collect($page->toArray()['props']['permissionGroups']['data']);
                $this->assertTrue($groups->pluck('id')->contains($empty->id));
                $loaded = $groups->firstWhere('id', $group->id);
                $this->assertSame($permission->id, $loaded['permissions'][0]['id']);
                $this->assertSame(1, $loaded['permissions'][0]['discord_roles_count']);
            });
    }

    #[Test]
    public function manage_counts_only_roles_that_grant_permissions(): void
    {
        $permission = Permission::factory()->create();
        $granting = DiscordRole::factory()->grantsPermissions()->create();
        $nonGranting = DiscordRole::factory()->create(['grants_permissions' => false]);
        $granting->permissions()->attach($permission);
        $nonGranting->permissions()->attach($permission);

        $this->actingAs($this->admin)->get(route('management.permissions.manage'))
            ->assertInertia(function (Assert $page) use ($permission) {
                $loaded = collect($page->toArray()['props']['ungroupedPermissions']['data'])->firstWhere('id', $permission->id);
                $this->assertSame(1, $loaded['discord_roles_count']);
            });
    }

    // ==================== update ====================

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge(['label' => 'Plan raids', 'description' => 'Lets officers plan raids.', 'permission_group_id' => null], $overrides);
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $permission = Permission::factory()->create(['label' => 'Original']);

        $this->patch(route('management.permissions.update', $permission), $this->validPayload())->assertRedirect('/login');

        $this->assertSame('Original', $permission->fresh()->label);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_officers_who_are_not_admins(): void
    {
        $permission = Permission::factory()->create();

        $this->actingAs($this->officer)->patch(route('management.permissions.update', $permission), $this->validPayload())
            ->assertForbidden();
    }

    #[Test]
    public function update_saves_the_label_description_and_group(): void
    {
        $group = PermissionGroup::factory()->create();
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)->from(route('management.permissions.manage'))
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['permission_group_id' => $group->id]))
            ->assertRedirect(route('management.permissions.manage'))
            ->assertSessionHas('success', 'Saved Plan raids.');

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'label' => 'Plan raids',
            'description' => 'Lets officers plan raids.',
            'permission_group_id' => $group->id,
        ]);
    }

    #[Test]
    public function update_can_ungroup_a_permission(): void
    {
        $group = PermissionGroup::factory()->create();
        $permission = Permission::factory()->create(['permission_group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['permission_group_id' => null]));

        $this->assertNull($permission->fresh()->permission_group_id);
    }

    #[Test]
    public function update_ignores_an_attempt_to_change_the_key(): void
    {
        $permission = Permission::factory()->create(['name' => 'original-key']);

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['name' => 'hacked']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Plan raids', $permission->fresh()->label);
        $this->assertSame('original-key', $permission->fresh()->name);
    }

    #[Test]
    public function update_is_visible_through_the_permission_cache(): void
    {
        $permission = Permission::factory()->create(['name' => 'cached-key', 'label' => 'Old label']);
        Permission::findByName('cached-key');

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload());

        $this->assertSame('Plan raids', Permission::findByName('cached-key')->label);
    }

    #[Group('validation')]
    #[Test]
    public function update_requires_a_label(): void
    {
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['label' => '']))
            ->assertSessionHasErrors('label');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_a_label_over_100_characters(): void
    {
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['label' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('label');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_a_description_over_160_characters(): void
    {
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['description' => str_repeat('a', 161)]))
            ->assertSessionHasErrors('description');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_an_unknown_group(): void
    {
        $permission = Permission::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.permissions.update', $permission), $this->validPayload(['permission_group_id' => (string) Str::uuid()]))
            ->assertSessionHasErrors('permission_group_id');
    }
}
