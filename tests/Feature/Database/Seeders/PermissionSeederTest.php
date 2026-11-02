<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('auth')]
class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{name: string, guard_name: string, label: string, description: string}> */
    private array $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $seeder = new PermissionSeeder;
        $reflection = new \ReflectionProperty($seeder, 'permissions');
        $this->permissions = $reflection->getValue($seeder);
    }

    // ==================== schema validation ====================

    #[Test]
    public function it_defines_the_warcraft_logs_permissions(): void
    {
        $names = collect($this->permissions)->pluck('name')->filter(fn (string $name): bool => str_contains($name, 'warcraft-logs'))->sort()->values()->all();

        $this->assertSame(['create-warcraft-logs-guilds', 'delete-warcraft-logs-guilds', 'update-warcraft-logs-guilds', 'update-warcraft-logs-tags', 'view-warcraft-logs-guilds'], $names);
    }

    #[Test]
    public function it_no_longer_defines_the_manage_boss_strategies_permission(): void
    {
        $this->assertNotContains('manage-boss-strategies', array_column($this->permissions, 'name'));
    }

    #[Test]
    public function seeder_deletes_the_retired_manage_boss_strategies_permission(): void
    {
        Permission::create(['name' => 'manage-boss-strategies', 'guard_name' => 'web']);

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseMissing('permissions', ['name' => 'manage-boss-strategies']);
    }

    #[Test]
    public function every_permission_entry_has_a_name_key(): void
    {
        foreach ($this->permissions as $index => $permission) {
            $this->assertArrayHasKey('name', $permission, "Permission at index {$index} is missing the 'name' key.");
        }
    }

    #[Test]
    public function every_permission_name_is_a_non_empty_string(): void
    {
        foreach ($this->permissions as $index => $permission) {
            $this->assertIsString($permission['name'], "Permission at index {$index} has a non-string 'name'.");
            $this->assertNotEmpty($permission['name'], "Permission at index {$index} has an empty 'name'.");
        }
    }

    #[Test]
    public function every_permission_entry_has_a_guard_name_key(): void
    {
        foreach ($this->permissions as $index => $permission) {
            $this->assertArrayHasKey('guard_name', $permission, "Permission at index {$index} is missing the 'guard_name' key.");
        }
    }

    #[Test]
    public function every_permission_guard_name_is_a_non_empty_string(): void
    {
        foreach ($this->permissions as $index => $permission) {
            $this->assertIsString($permission['guard_name'], "Permission at index {$index} has a non-string 'guard_name'.");
            $this->assertNotEmpty($permission['guard_name'], "Permission at index {$index} has an empty 'guard_name'.");
        }
    }

    #[Test]
    public function permission_names_are_unique(): void
    {
        $names = array_column($this->permissions, 'name');
        $uniqueNames = array_unique($names);

        $this->assertCount(count($names), $uniqueNames, 'Duplicate permission names found in the $permissions array.');
    }

    #[Test]
    public function permission_entries_contain_no_unexpected_keys(): void
    {
        $allowedKeys = ['name', 'guard_name', 'label', 'description'];

        foreach ($this->permissions as $index => $permission) {
            $unexpectedKeys = array_diff(array_keys($permission), $allowedKeys);

            $this->assertEmpty(
                $unexpectedKeys,
                "Permission at index {$index} contains unexpected keys: ".implode(', ', $unexpectedKeys)
            );
        }
    }

    #[Test]
    public function every_grouped_permission_is_defined(): void
    {
        $groups = (new \ReflectionMethod(PermissionSeeder::class, 'groups'))->invoke(new PermissionSeeder);
        $names = array_column($this->permissions, 'name');

        foreach ($groups as $group => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                $this->assertContains($permissionName, $names, "{$group} lists undefined permission {$permissionName}.");
            }
        }
    }

    // ==================== seeder behaviour ====================

    #[Test]
    public function seeder_creates_all_defined_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach ($this->permissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission['name'],
                'guard_name' => $permission['guard_name'],
            ]);
        }
    }

    #[Test]
    public function seeder_is_idempotent_and_can_be_run_multiple_times(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('permissions', count($this->permissions));
    }

    #[Test]
    public function seeder_deletes_stale_permissions_no_longer_in_the_list(): void
    {
        Permission::firstOrCreate(['name' => 'stale-permission', 'guard_name' => 'web']);

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseMissing('permissions', ['name' => 'stale-permission']);
    }

    #[Test]
    public function seeder_does_not_delete_stale_permissions_on_other_guards(): void
    {
        Permission::firstOrCreate(['name' => 'stale-permission', 'guard_name' => 'api']);

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'stale-permission', 'guard_name' => 'api']);
    }

    #[Test]
    public function every_permission_has_a_label_and_a_description_within_160_characters(): void
    {
        foreach ($this->permissions as $permission) {
            $this->assertNotEmpty($permission['label'] ?? null, "{$permission['name']} has no label.");
            $this->assertNotEmpty($permission['description'] ?? null, "{$permission['name']} has no description.");
            $this->assertLessThanOrEqual(160, mb_strlen($permission['description']), "{$permission['name']}'s description is too long.");
        }
    }

    #[Test]
    public function seeder_creates_the_starting_groups_for_new_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertSame(
            ['Loot Bias Tool', 'Raid Management', 'Planned Absences', 'Character Management', 'Warcraft Logs'],
            PermissionGroup::ordered()->pluck('name')->all()
        );
        $this->assertSame('Raid Management', Permission::findByName('manage-raid-plans')->permissionGroup->name);
        $this->assertNull(Permission::findByName('edit-datasets')->permission_group_id);
    }

    #[Test]
    public function seeder_leaves_an_existing_permissions_group_alone(): void
    {
        $this->seed(PermissionSeeder::class);
        Permission::findByName('manage-raid-plans')->update(['permission_group_id' => null]);

        $this->seed(PermissionSeeder::class);

        $this->assertNull(Permission::findByName('manage-raid-plans')->permission_group_id);
        $this->assertDatabaseCount('permission_groups', 5);
    }

    #[Test]
    public function seeder_puts_a_new_permission_into_its_existing_group(): void
    {
        $this->seed(PermissionSeeder::class);
        $group = Permission::findByName('view-attendance')->permissionGroup;
        Permission::findByName('view-attendance')->delete();

        $this->seed(PermissionSeeder::class);

        $this->assertSame($group->id, Permission::findByName('view-attendance')->permission_group_id);
        $this->assertDatabaseCount('permission_groups', 5);
    }

    #[Test]
    public function seeder_keeps_admin_edits_to_existing_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $elsewhere = PermissionGroup::factory()->create();
        Permission::findByName('manage-raid-plans')->update([
            'label' => 'Plan raids', 'description' => 'Custom.', 'permission_group_id' => $elsewhere->id,
        ]);

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => 'manage-raid-plans', 'label' => 'Plan raids', 'description' => 'Custom.', 'permission_group_id' => $elsewhere->id,
        ]);
    }

    #[Test]
    public function seeder_keeps_a_cleared_description(): void
    {
        $this->seed(PermissionSeeder::class);
        Permission::findByName('view-attendance')->update(['label' => 'View attendance', 'description' => null]);

        $this->seed(PermissionSeeder::class);

        $this->assertNull(Permission::findByName('view-attendance')->description);
    }

    #[Test]
    public function seeder_fills_labels_and_descriptions_on_unedited_permissions(): void
    {
        Permission::create(['name' => 'view-attendance', 'guard_name' => 'web']);

        $this->seed(PermissionSeeder::class);

        $permission = Permission::findByName('view-attendance');
        $this->assertSame('View attendance', $permission->label);
        $this->assertSame("See everyone's raid attendance.", $permission->description);
    }

    #[Test]
    public function seeder_grants_no_permissions_to_any_role(): void
    {
        DiscordRole::factory()->officer()->create();

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseEmpty('discord_role_has_permissions');
    }

    #[Test]
    public function seeder_leaves_role_assignments_untouched(): void
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $this->seed(PermissionSeeder::class);
        $role->givePermissionTo('view-attendance');

        $this->seed(PermissionSeeder::class);

        $this->assertSame(['view-attendance'], $role->fresh()->permissions->pluck('name')->all());
    }
}
