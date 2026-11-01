<?php

namespace Tests\Feature\Dashboard;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class PermissionGroupControllerTest extends DashboardTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->officer()->admin()->create();
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->get(route('management.permission-groups.index'))->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)->get(route('management.permission-groups.index'))->assertForbidden();
    }

    #[Test]
    public function index_lists_groups_in_order_with_permission_counts(): void
    {
        $second = PermissionGroup::factory()->create();
        $first = PermissionGroup::factory()->create();
        $second->update(['sort_order' => 2]);
        $first->update(['sort_order' => 1]);
        Permission::factory()->count(2)->create(['permission_group_id' => $first->id]);

        $this->actingAs($this->admin)->get(route('management.permission-groups.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/Permissions/Groups')
                ->where('permissionGroups.data.0.id', $first->id)
                ->where('permissionGroups.data.1.id', $second->id)
                ->where('permissionGroups.data.0.permissions_count', 2)
            );
    }

    // ==================== store ====================

    #[Test]
    public function store_requires_authentication(): void
    {
        $this->post(route('management.permission-groups.store'), ['name' => 'Loot'])->assertRedirect('/login');

        $this->assertDatabaseMissing('permission_groups', ['name' => 'Loot']);
    }

    #[Group('authorization')]
    #[Test]
    public function store_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)
            ->post(route('management.permission-groups.store'), ['name' => 'Seasonal events'])
            ->assertForbidden();

        $this->assertDatabaseCount('permission_groups', 0);
    }

    #[Test]
    public function store_adds_a_group_to_the_end(): void
    {
        PermissionGroup::factory()->count(2)->create();

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->post(route('management.permission-groups.store'), ['name' => 'Seasonal events'])
            ->assertRedirect(route('management.permission-groups.index'))
            ->assertSessionHas('success', 'Added Seasonal events.');

        $this->assertSame(3, PermissionGroup::where('name', 'Seasonal events')->value('sort_order'));
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_duplicate_name(): void
    {
        PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->post(route('management.permission-groups.store'), ['name' => 'Loot'])
            ->assertInvalid(['name']);

        $this->assertDatabaseCount('permission_groups', 1);
    }

    #[Group('validation')]
    #[Test]
    public function store_requires_a_name(): void
    {
        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->post(route('management.permission-groups.store'), [])
            ->assertInvalid(['name']);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_name_over_50_characters(): void
    {
        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->post(route('management.permission-groups.store'), ['name' => str_repeat('a', 51)])
            ->assertInvalid(['name']);
    }

    // ==================== update ====================

    #[Group('authorization')]
    #[Test]
    public function update_requires_authentication(): void
    {
        $group = PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->patch(route('management.permission-groups.update', $group), ['name' => 'Renamed'])->assertRedirect('/login');

        $this->assertSame('Loot', $group->fresh()->name);
    }

    #[Test]
    public function update_forbids_officers_who_are_not_admins(): void
    {
        $group = PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->actingAs($this->officer)
            ->patch(route('management.permission-groups.update', $group), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertSame('Loot', $group->fresh()->name);
    }

    #[Test]
    public function update_renames_a_group(): void
    {
        $group = PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->patch(route('management.permission-groups.update', $group), ['name' => 'Loot council'])
            ->assertRedirect(route('management.permission-groups.index'))
            ->assertSessionHas('success', 'Saved Loot council.');

        $this->assertSame('Loot council', $group->fresh()->name);
    }

    #[Test]
    public function update_allows_keeping_the_same_name(): void
    {
        $group = PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->patch(route('management.permission-groups.update', $group), ['name' => 'Loot'])
            ->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_another_groups_name(): void
    {
        PermissionGroup::factory()->create(['name' => 'Loot']);
        $group = PermissionGroup::factory()->create(['name' => 'Raids']);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->patch(route('management.permission-groups.update', $group), ['name' => 'Loot'])
            ->assertInvalid(['name']);

        $this->assertSame('Raids', $group->fresh()->name);
    }

    // ==================== reorder ====================

    #[Test]
    public function reorder_requires_authentication(): void
    {
        $groups = PermissionGroup::factory()->count(2)->create();
        $original = $groups->pluck('sort_order', 'id')->all();

        $this->patch(route('management.permission-groups.reorder'), ['permission_group_ids' => $groups->pluck('id')->reverse()->values()->all()])
            ->assertRedirect('/login');

        $this->assertSame($original, PermissionGroup::query()->pluck('sort_order', 'id')->all());
    }

    #[Group('authorization')]
    #[Test]
    public function reorder_forbids_officers_who_are_not_admins(): void
    {
        $groups = PermissionGroup::factory()->count(2)->create();

        $this->actingAs($this->officer)
            ->patch(route('management.permission-groups.reorder'), ['permission_group_ids' => $groups->pluck('id')->reverse()->values()->all()])
            ->assertForbidden();
    }

    #[Test]
    public function reorder_stores_the_new_order(): void
    {
        [$first, $second, $third] = PermissionGroup::factory()->count(3)->create();

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->patch(route('management.permission-groups.reorder'), ['permission_group_ids' => [$third->id, $second->id, $first->id]])
            ->assertRedirect(route('management.permission-groups.index'));

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
        $this->assertSame(3, $first->fresh()->sort_order);
    }

    #[Group('validation')]
    #[Test]
    public function reorder_rejects_a_list_missing_a_group(): void
    {
        [$first, $second, $third] = PermissionGroup::factory()->count(3)->create();

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->patch(route('management.permission-groups.reorder'), ['permission_group_ids' => [$second->id, $first->id]])
            ->assertInvalid(['permission_group_ids' => 'The group list is out of date. Reload the page and try again.']);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
    }

    // ==================== destroy ====================

    #[Group('authorization')]
    #[Test]
    public function destroy_requires_authentication(): void
    {
        $group = PermissionGroup::factory()->create();

        $this->delete(route('management.permission-groups.destroy', $group))->assertRedirect('/login');

        $this->assertModelExists($group);
    }

    #[Test]
    public function destroy_forbids_officers_who_are_not_admins(): void
    {
        $group = PermissionGroup::factory()->create();

        $this->actingAs($this->officer)
            ->delete(route('management.permission-groups.destroy', $group))
            ->assertForbidden();

        $this->assertModelExists($group);
    }

    #[Test]
    public function destroy_deletes_an_empty_group(): void
    {
        $group = PermissionGroup::factory()->create(['name' => 'Loot']);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->delete(route('management.permission-groups.destroy', $group))
            ->assertRedirect(route('management.permission-groups.index'))
            ->assertSessionHas('success', 'Deleted Loot.');

        $this->assertModelMissing($group);
    }

    #[Test]
    public function destroy_refuses_a_group_that_still_has_permissions(): void
    {
        $group = PermissionGroup::factory()->create();
        $permission = Permission::factory()->create(['permission_group_id' => $group->id]);

        $this->actingAs($this->admin)->from(route('management.permission-groups.index'))
            ->delete(route('management.permission-groups.destroy', $group))
            ->assertRedirect(route('management.permission-groups.index'))
            ->assertSessionHas('error', "Move this group's permissions to another group first.");

        $this->assertModelExists($group);
        $this->assertSame($group->id, $permission->fresh()->permission_group_id);
    }
}
