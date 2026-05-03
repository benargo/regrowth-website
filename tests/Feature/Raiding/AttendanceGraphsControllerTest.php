<?php

namespace Tests\Feature\Raiding;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceGraphsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags(['attendance', 'reports'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-attendance', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    // ==================== index: Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('raiding.attendance.graphs.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertOk();
    }

    // ==================== index: scatterPoints ====================

    #[Test]
    public function index_scatter_points_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('scatterPoints')
        );
    }

    #[Test]
    public function index_scatter_points_returns_one_entry_per_player_with_expected_keys(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Illidan', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(2)]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('scatterPoints', 1)
                ->where('scatterPoints.0.id', $character->id)
                ->where('scatterPoints.0.name', 'Illidan')
                ->where('scatterPoints.0.percentage', 100)
                ->where('scatterPoints.0.raidsTotal', 1)
                ->where('scatterPoints.0.raidsAttended', 1)
                ->where('scatterPoints.0.benched', 0)
                ->where('scatterPoints.0.plannedAbsences', 0)
                ->where('scatterPoints.0.otherAbsences', 0)
            )
        );
    }

    #[Test]
    public function index_scatter_points_counts_benched_and_other_absences(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Maiev', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $r1 = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(5)]);
        $r1->characters()->attach($character->id, ['presence' => 1]);

        $r2 = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(4)]);
        $r2->characters()->attach($character->id, ['presence' => 2]);

        $r3 = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(3)]);
        $r3->characters()->attach($character->id, ['presence' => 0]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('scatterPoints.0.raidsAttended', 1)
                ->where('scatterPoints.0.benched', 1)
                ->where('scatterPoints.0.otherAbsences', 1)
                ->where('scatterPoints.0.raidsTotal', 3)
            )
        );
    }

    #[Test]
    public function index_scatter_points_counts_planned_absences_covering_raids(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Tyrande', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $attendedReport = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(10)]);
        $attendedReport->characters()->attach($character->id, ['presence' => 1]);

        $coveredReport = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(3)]);
        $coveredReport->characters()->attach($character->id, ['presence' => 0]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->subDays(4),
            'end_date' => now()->subDays(2),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('scatterPoints.0.plannedAbsences', 1)
                ->where('scatterPoints.0.raidsTotal', 2)
            )
        );
    }

    #[Test]
    public function index_scatter_points_returns_empty_array_when_no_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('scatterPoints', [])
            )
        );
    }
}
