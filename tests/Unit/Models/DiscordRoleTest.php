<?php

namespace Tests\Unit\Models;

use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\Support\ModelTestCase;

class DiscordRoleTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordRole::class;
    }

    #[Test]
    public function it_uses_discord_roles_table(): void
    {
        $model = new DiscordRole;

        $this->assertSame('discord_roles', $model->getTable());
    }

    #[Test]
    public function it_uses_string_primary_key(): void
    {
        $model = new DiscordRole;

        $this->assertSame('id', $model->getKeyName());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DiscordRole;

        $this->assertFillable($model, [
            'id',
            'name',
            'position',
            'is_visible',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new DiscordRole;

        $this->assertCasts($model, [
            'id' => 'string',
            'position' => 'integer',
            'is_visible' => 'boolean',
        ]);
    }

    #[Test]
    public function it_defaults_is_visible_to_false(): void
    {
        $model = new DiscordRole;

        $this->assertFalse($model->is_visible);
    }

    #[Test]
    public function it_can_create_a_discord_role(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        $this->assertTableHas([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);
        $this->assertModelExists($role);
    }

    #[Test]
    public function it_enforces_unique_position_constraint(): void
    {
        $this->create([
            'id' => '123456789012345678',
            'name' => 'RoleA',
            'position' => 50,
        ]);

        $this->assertUniqueConstraint(function () {
            $this->create([
                'id' => '123456789012345679',
                'name' => 'RoleB',
                'position' => 50,
            ]);
        });
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        $this->assertNotNull($role->created_at);
        $this->assertNotNull($role->updated_at);
    }

    #[Test]
    public function users_returns_belongs_to_many_relationship(): void
    {
        $role = new DiscordRole;

        $this->assertInstanceOf(BelongsToMany::class, $role->users());
    }

    #[Test]
    public function users_returns_associated_users(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->discordRoles()->attach($role->id);
        $user2->discordRoles()->attach($role->id);

        $this->assertCount(2, $role->users);
        $this->assertTrue($role->users->contains($user1));
        $this->assertTrue($role->users->contains($user2));
    }

    #[Test]
    public function users_returns_empty_collection_when_no_users_exist(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        $this->assertCount(0, $role->users);
    }

    #[Test]
    public function permissions_returns_belongs_to_many_relationship(): void
    {
        $role = new DiscordRole;

        $this->assertInstanceOf(BelongsToMany::class, $role->permissions());
    }

    #[Test]
    public function it_can_be_given_a_permission(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        Permission::firstOrCreate(['name' => 'test-permission', 'guard_name' => 'web']);

        $role->givePermissionTo('test-permission');

        $this->assertTrue($role->hasPermissionTo('test-permission'));
    }

    #[Test]
    public function it_can_have_a_permission_revoked(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        Permission::firstOrCreate(['name' => 'test-permission', 'guard_name' => 'web']);

        $role->givePermissionTo('test-permission');
        $this->assertTrue($role->hasPermissionTo('test-permission'));

        $role->revokePermissionTo('test-permission');
        $role->load('permissions');
        $this->assertFalse($role->hasPermissionTo('test-permission'));
    }

    #[Test]
    public function has_permission_to_returns_false_when_permission_not_assigned(): void
    {
        $role = $this->create([
            'id' => '123456789012345678',
            'name' => 'TestRole',
            'position' => 50,
        ]);

        Permission::firstOrCreate(['name' => 'test-permission', 'guard_name' => 'web']);

        $this->assertFalse($role->hasPermissionTo('test-permission'));
    }
}
