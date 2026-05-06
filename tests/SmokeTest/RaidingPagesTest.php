<?php

namespace Tests\SmokeTest;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RaidingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );

        foreach (['view-attendance', 'view-planned-absences', 'create-planned-absences', 'update-planned-absences', 'view-raid-plans'] as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            $officerRole->givePermissionTo($permission);
        }

        $this->mock(Discord::class, function ($mock) {
            $mock->shouldReceive('getChannel')->andReturn(new Channel(
                id: '123456789',
                name: 'raid-planning',
                position: 1,
            ));
        });
    }

    #[Test]
    public function raiding_index_loads(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function raiding_index_loads_for_unauthenticated_users(): void
    {
        $response = $this->get(route('raiding.index'));

        $response->assertOk();
    }

    #[Test]
    public function attendance_dashboard_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function attendance_matrix_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function report_show_loads(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function planned_absences_index_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function planned_absences_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get(route('raiding.absences.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function planned_absences_index_returns_403_for_users_without_permission(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function planned_absences_create_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function planned_absences_create_redirects_unauthenticated_users(): void
    {
        $response = $this->get(route('raiding.absences.create'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function planned_absences_create_returns_403_for_users_without_permission(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function planned_absences_edit_loads(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raiding.absences.edit', $absence));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function planned_absences_edit_redirects_unauthenticated_users(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->get(route('raiding.absences.edit', $absence));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function planned_absences_edit_returns_403_for_users_without_permission(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->get(route('raiding.absences.edit', $absence));

        $response->assertForbidden();
    }

    #[Test]
    public function event_show_loads(): void
    {
        $user = User::factory()->officer()->create();
        $event = \App\Models\Event::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.plans.show', $event));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function event_show_redirects_unauthenticated_users(): void
    {
        $event = \App\Models\Event::factory()->create();

        $response = $this->get(route('raiding.plans.show', $event));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function event_show_returns_403_for_users_without_permission(): void
    {
        $member = User::factory()->member()->create();
        $event = \App\Models\Event::factory()->create();

        $response = $this->actingAs($member)->get(route('raiding.plans.show', $event));

        $response->assertForbidden();
    }
}
