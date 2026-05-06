<?php

namespace Tests\Feature\Raiding;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceDashboardControllerTest extends TestCase
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
    public function invoke_requires_authentication(): void
    {
        $response = $this->get(route('raiding.attendance.dashboard'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function invoke_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertOk();
    }

    // ==================== index: Props ====================

    #[Test]
    public function invoke_returns_latest_report_date(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => '2025-03-07 12:00:00']);
        Report::factory()->withGuildTag($tag)->create(['start_time' => '2025-03-15 12:00:00']);
        Report::factory()->withGuildTag($tag)->create(['start_time' => '2025-03-10 12:00:00']);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('latestReportDate', '15 Mar 2025')
        );
    }

    #[Test]
    public function invoke_returns_null_latest_report_date_when_no_reports(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('latestReportDate', null)
        );
    }

    #[Test]
    public function invoke_stats_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('stats')
        );
    }

    #[Test]
    public function invoke_deferred_stats_contains_expected_keys(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.percentageGroups')
                ->has('stats.droppingOff')
                ->has('stats.pickingUp')
                ->has('stats.totalPlayers')
                ->has('stats.totalMains')
                ->has('stats.totalLinkedCharacters')
                ->has('stats.phaseAttendance')
                ->has('stats.previousPhaseAttendance')
                ->has('stats.benchedLastWeek')
                ->has('stats.upcomingAbsences')
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_returns_future_absences_in_order(): void
    {
        $rank = GuildRank::factory()->create();
        $characterA = Character::factory()->main()->create(['name' => 'Arthas', 'rank_id' => $rank->id]);
        $characterB = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        PlannedAbsence::factory()->create([
            'character_id' => $characterB->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
        ]);
        PlannedAbsence::factory()->create([
            'character_id' => $characterA->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(4),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.upcomingAbsences', 2)
                ->where('stats.upcomingAbsences.0.character.name', 'Arthas')
                ->where('stats.upcomingAbsences.1.character.name', 'Jaina')
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_excludes_past_absences(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Sylvanas', 'rank_id' => $rank->id]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.upcomingAbsences', [])
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_limited_to_four(): void
    {
        $rank = GuildRank::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $character = Character::factory()->main()->create(['rank_id' => $rank->id]);
            PlannedAbsence::factory()->create([
                'character_id' => $character->id,
                'start_date' => now()->addDays($i + 1),
                'end_date' => now()->addDays($i + 2),
            ]);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.upcomingAbsences', 4)
            )
        );
    }

    #[Test]
    public function invoke_classifies_above_80_correctly(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        for ($i = 0; $i < 5; $i++) {
            $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(10 + $i)]);
            $report->characters()->attach($character->id, ['presence' => 1]);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.percentageGroups.>=80', 1)
                ->where('stats.percentageGroups.>=80.0.name', 'Thrall')
                ->missing('stats.percentageGroups.50-80')
                ->where('stats.totalPlayers', 1)
            )
        );
    }

    #[Test]
    public function invoke_classifies_dropping_off_correctly(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Vashj', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        // 4 older raids attended
        for ($i = 0; $i < 4; $i++) {
            $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(20 + $i)]);
            $report->characters()->attach($character->id, ['presence' => 1]);
        }

        // 4 recent raids not attended
        for ($i = 0; $i < 4; $i++) {
            Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(1 + $i)]);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.droppingOff', 1)
                ->where('stats.droppingOff.0.name', 'Vashj')
            )
        );
    }

    #[Test]
    public function invoke_benched_last_week_groups_characters_by_tag(): void
    {
        $rank = GuildRank::factory()->create();
        $kael = Character::factory()->main()->create(['name' => 'Kael', 'rank_id' => $rank->id]);
        $illidan = Character::factory()->main()->create(['name' => 'Illidan', 'rank_id' => $rank->id]);

        $monday = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Monday']);
        $tuesday = GuildTag::factory()->countsAttendance()->withoutPhase()->create(['name' => 'Tuesday']);

        $mondayReport = Report::factory()->withGuildTag($monday)->create(['start_time' => now()->subDays(2)]);
        $mondayReport->characters()->attach($kael->id, ['presence' => 2]);

        $tuesdayReport = Report::factory()->withGuildTag($tuesday)->create(['start_time' => now()->subDays(1)]);
        $tuesdayReport->characters()->attach($illidan->id, ['presence' => 2]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.benchedLastWeek.Monday', 1)
                ->where('stats.benchedLastWeek.Monday.0.name', 'Kael')
                ->has('stats.benchedLastWeek.Tuesday', 1)
                ->where('stats.benchedLastWeek.Tuesday.0.name', 'Illidan')
            )
        );
    }

    #[Test]
    public function invoke_benched_last_week_excludes_character_from_older_report(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Illidan', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(10)]);
        $report->characters()->attach($character->id, ['presence' => 2]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.benchedLastWeek', [])
            )
        );
    }

    #[Test]
    public function invoke_classifies_picking_up_correctly(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Maiev', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $debutReport = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(30)]);
        $debutReport->characters()->attach($character->id, ['presence' => 1]);

        for ($i = 0; $i < 3; $i++) {
            Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(15 + $i)]);
        }

        for ($i = 0; $i < 4; $i++) {
            $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(1 + $i)]);
            $report->characters()->attach($character->id, ['presence' => 1]);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.pickingUp', 1)
                ->where('stats.pickingUp.0.name', 'Maiev')
            )
        );
    }

    #[Test]
    public function invoke_phase_attendance_null_when_no_phase_started(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.phaseAttendance', null)
                ->where('stats.previousPhaseAttendance', null)
            )
        );
    }

    #[Test]
    public function invoke_phase_attendance_only_counts_reports_since_current_phase_start(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        Phase::factory()->create(['start_date' => now()->subDays(14)]);

        $preReport = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(20)]);
        $preReport->characters()->attach($character->id, ['presence' => 1]);

        $postReport1 = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(10)]);
        $postReport1->characters()->attach($character->id, ['presence' => 1]);

        Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(5)]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.phaseAttendance', 50)
            )
        );
    }

    #[Test]
    public function invoke_total_players_breakdown_counts_mains_and_linked_separately(): void
    {
        $rank = GuildRank::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $main1 = Character::factory()->main()->create(['rank_id' => $rank->id]);
        $main2 = Character::factory()->main()->create(['rank_id' => $rank->id]);
        $linked = Character::factory()->create(['is_main' => false, 'rank_id' => $rank->id]);

        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(1)]);
        $report->characters()->attach($main1->id, ['presence' => 1]);
        $report->characters()->attach($main2->id, ['presence' => 1]);
        $report->characters()->attach($linked->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.totalPlayers', 3)
                ->where('stats.totalMains', 2)
                ->where('stats.totalLinkedCharacters', 1)
            )
        );
    }

    #[Test]
    public function invoke_previous_phase_attendance_bounded_between_phase_starts(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Vol\'jin', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        Phase::factory()->create(['number' => 1, 'start_date' => now()->subDays(30)]);
        Phase::factory()->create(['number' => 2, 'start_date' => now()->subDays(7)]);

        $phase1Report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(20)]);
        $phase1Report->characters()->attach($character->id, ['presence' => 1]);

        $phase2Report = Report::factory()->withGuildTag($tag)->create(['start_time' => now()->subDays(3)]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.previousPhaseAttendance', 100)
                ->where('stats.phaseAttendance', null)
            )
        );
    }
}
