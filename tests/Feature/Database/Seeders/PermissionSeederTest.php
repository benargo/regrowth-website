<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\DiscordRole;
use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{name: string, guard_name: string, group?: string}> */
    private array $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $seeder = new PermissionSeeder;
        $reflection = new \ReflectionProperty($seeder, 'permissions');
        $this->permissions = $reflection->getValue($seeder);
    }

    // ==================== Schema Validation ====================

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
    public function every_permission_group_when_present_is_in_slug_format(): void
    {
        foreach ($this->permissions as $index => $permission) {
            if (! isset($permission['group'])) {
                continue;
            }

            $this->assertIsString($permission['group'], "Permission at index {$index} has a non-string 'group'.");
            $this->assertNotEmpty($permission['group'], "Permission at index {$index} has an empty 'group'.");
            $this->assertSame(
                Str::slug($permission['group']),
                $permission['group'],
                "Permission at index {$index} has a 'group' value that is not slug format: '{$permission['group']}'."
            );
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
        $allowedKeys = ['name', 'guard_name', 'group'];

        foreach ($this->permissions as $index => $permission) {
            $unexpectedKeys = array_diff(array_keys($permission), $allowedKeys);

            $this->assertEmpty(
                $unexpectedKeys,
                "Permission at index {$index} contains unexpected keys: ".implode(', ', $unexpectedKeys)
            );
        }
    }

    // ==================== Seeder Behaviour ====================

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
    public function seeder_updates_group_on_existing_permissions(): void
    {
        $first = $this->permissions[0];
        Permission::firstOrCreate(
            ['name' => $first['name'], 'guard_name' => $first['guard_name']],
            ['group' => 'old_group']
        );

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'name' => $first['name'],
            'group' => $first['group'] ?? null,
        ]);
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
    public function seeder_assigns_all_permissions_to_the_officer_role(): void
    {
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5]
        );

        $this->seed(PermissionSeeder::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            $this->assertTrue(
                $officerRole->fresh()->hasPermissionTo($permission['name']),
                "Officer role does not have permission '{$permission['name']}'."
            );
        }
    }

    #[Test]
    public function seeder_does_not_assign_permissions_when_officer_role_does_not_exist(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('role_has_permissions', 0);
    }
}
