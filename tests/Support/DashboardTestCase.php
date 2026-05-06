<?php

namespace Tests\Support;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class DashboardTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cached permissions and roles to ensure a clean state for each test.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-datasets', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-boss-strategies', 'guard_name' => 'web']));

        $this->officer = User::factory()->officer()->create();
    }
}
