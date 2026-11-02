<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('auth')]
class PermissionGroupTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PermissionGroup::class;
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $this->assertFillableAttribute(new PermissionGroup, ['name', 'sort_order']);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $this->assertHidden(new PermissionGroup, ['created_at', 'updated_at']);
    }

    #[Test]
    public function it_appends_new_groups_to_the_end_of_the_order(): void
    {
        $first = $this->create();
        $second = $this->create();

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
    }

    #[Test]
    public function it_requires_a_unique_name(): void
    {
        $existing = $this->create();

        $this->assertUniqueConstraint(fn () => $this->create(['name' => $existing->name]));
    }

    #[Test]
    public function it_has_many_permissions(): void
    {
        $this->assertRelation(new PermissionGroup, 'permissions', HasMany::class);
    }

    #[Test]
    public function deleting_a_group_leaves_its_permissions_ungrouped(): void
    {
        $group = $this->create();
        $permission = Permission::factory()->for($group, 'permissionGroup')->create();

        $group->delete();

        $this->assertNull($permission->fresh()->permission_group_id);
    }
}
