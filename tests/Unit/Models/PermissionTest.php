<?php

namespace Tests\Unit\Models;

use App\Events\PermissionUpdated;
use App\Models\Permission;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PermissionTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Permission::class;
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new Permission;

        $this->assertHidden($model, ['guard_name', 'created_at', 'updated_at']);
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Permission;

        $this->assertFillable($model, ['name', 'guard_name', 'group']);
    }

    #[Test]
    public function it_can_create_a_permission_without_a_group(): void
    {
        $permission = $this->create(['name' => 'view-reports']);

        $this->assertTableHas(['name' => 'view-reports', 'group' => null]);
        $this->assertModelExists($permission);
    }

    #[Test]
    public function it_can_create_a_permission_with_a_group(): void
    {
        $permission = $this->create(['name' => 'view-reports', 'group' => 'raids']);

        $this->assertTableHas(['name' => 'view-reports', 'group' => 'raids']);
        $this->assertModelExists($permission);
    }

    #[Test]
    public function group_is_converted_to_slug_on_set(): void
    {
        $permission = $this->create(['name' => 'view-reports', 'group' => 'My Group']);

        $this->assertSame('my-group', $permission->group);
        $this->assertTableHas(['name' => 'view-reports', 'group' => 'my-group']);
    }

    #[Test]
    public function group_converts_mixed_case_to_slug(): void
    {
        $permission = $this->create(['name' => 'view-reports', 'group' => 'LootCouncil']);

        $this->assertSame('lootcouncil', $permission->group);
    }

    #[Test]
    public function group_leaves_kebab_case_unchanged(): void
    {
        $permission = $this->create(['name' => 'view-reports', 'group' => 'loot-council']);

        $this->assertSame('loot-council', $permission->group);
    }

    #[Test]
    public function group_handles_null(): void
    {
        $permission = $this->create(['name' => 'view-reports', 'group' => null]);

        $this->assertNull($permission->group);
    }

    #[Test]
    public function factory_in_group_state_sets_group(): void
    {
        $permission = Permission::factory()->inGroup('Raids')->create(['name' => 'view-reports']);

        $this->assertSame('raids', $permission->group);
    }

    // ==================== events ====================

    #[Test]
    public function it_dispatches_permission_updated_event_on_update(): void
    {
        $permission = $this->create(['name' => 'view-reports']);
        Event::fake();

        $permission->update(['group' => 'raids']);

        Event::assertDispatched(PermissionUpdated::class);
    }
}
