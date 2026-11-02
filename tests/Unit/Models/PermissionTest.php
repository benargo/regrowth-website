<?php

namespace Tests\Unit\Models;

use App\Events\PermissionUpdated;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('auth')]
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

        $this->assertFillable($model, ['name', 'guard_name', 'label', 'description', 'permission_group_id']);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new Permission;

        $this->assertFillableAttribute($model, ['name', 'guard_name', 'label', 'description', 'permission_group_id']);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_belongs_to_a_permission_group(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new Permission)->permissionGroup());
    }

    #[Test]
    public function discord_roles_returns_belongs_to_many_relationship(): void
    {
        $permission = new Permission;

        $this->assertInstanceOf(BelongsToMany::class, $permission->discordRoles());
    }

    #[Test]
    public function discord_roles_returns_associated_discord_roles(): void
    {
        $permission = $this->create(['name' => 'manage-reports']);

        $role1 = DiscordRole::factory()->create();
        $role2 = DiscordRole::factory()->create();
        $permission->discordRoles()->attach([$role1->id, $role2->id]);

        $this->assertCount(2, $permission->discordRoles);
        $this->assertTrue($permission->discordRoles->contains($role1));
        $this->assertTrue($permission->discordRoles->contains($role2));
    }

    #[Test]
    public function discord_roles_returns_empty_collection_when_no_roles_exist(): void
    {
        $permission = $this->create(['name' => 'manage-reports']);

        $this->assertCount(0, $permission->discordRoles);
    }

    // ==================== events ====================

    #[Test]
    public function it_dispatches_permission_updated_event_on_update(): void
    {
        $permission = $this->create(['name' => 'manage-reports']);
        Event::fake();

        $permission->update(['label' => 'Manage reports']);

        Event::assertDispatched(PermissionUpdated::class);
    }
}
