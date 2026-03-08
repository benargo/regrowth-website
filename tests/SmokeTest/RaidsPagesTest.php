<?php

namespace Tests\SmokeTest;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use App\Models\WarcraftLogs\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RaidsPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );

        foreach (['view-attendance', 'view-reports'] as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            $officerRole->givePermissionTo($permission);
        }
    }

    public function test_attendance_dashboard_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_attendance_matrix_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_report_show_loads(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
