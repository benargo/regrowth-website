<?php

namespace Tests\Feature\Raiding;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Enums\PlannedAbsenceReason;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceDashboardControllerTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    private GuildRank $rank;

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

        $this->gameVersion = $this->makeAttendanceVersion();
        $this->rank = $this->makeRank($this->gameVersion);
    }

    // ==================== index: Access Control ====================

    #[Test]
    public function invoke_requires_authentication(): void
    {
        $response = $this->get(route('raiding.attendance.dashboard'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
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
        $this->makeReport($this->gameVersion, '2025-03-07 12:00:00');
        $this->makeReport($this->gameVersion, '2025-03-15 12:00:00');
        $this->makeReport($this->gameVersion, '2025-03-10 12:00:00');

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('latestReportDate', '2025-03-15')
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
        $this->makeReport($this->gameVersion, now()->subDay());
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
        $this->makeReport($this->gameVersion, now()->subDay());
        $characterA = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Arthas', 'is_main' => true]);
        $characterB = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Jaina', 'is_main' => true]);

        PlannedAbsence::factory()->create([
            'character_id' => $characterB->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
        ]);
        PlannedAbsence::factory()->create([
            'character_id' => $characterA->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(4),
            'reason' => PlannedAbsenceReason::Holiday,
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.upcomingAbsences.data', 2)
                ->where('stats.upcomingAbsences.data.0.character.name', 'Arthas')
                ->where('stats.upcomingAbsences.data.1.character.name', 'Jaina')
                ->where('stats.upcomingAbsences.data.0.reason.key', 'On holiday')
                ->where('stats.upcomingAbsences.data.0.reason.icon', 'umbrella-beach')
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_excludes_past_absences(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Sylvanas', 'is_main' => true]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.upcomingAbsences.data', 0)
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_are_not_limited(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());

        for ($i = 0; $i < 6; $i++) {
            $character = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
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
                ->has('stats.upcomingAbsences.data', 6)
            )
        );
    }

    #[Test]
    public function invoke_upcoming_absences_exclude_another_game_versions_characters(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());

        PlannedAbsence::factory()->create([
            'character_id' => Character::factory()->forGameVersion(GameVersion::factory()->create())->create()->id,
            'start_date' => now()->addDay(),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.upcomingAbsences.data', 0)
            )
        );
    }

    #[Test]
    public function invoke_classifies_above_80_correctly(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->attend($this->makeReport($this->gameVersion, now()->subDays(10 + $i)), $character);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.percentageGroups.>=80.data', 1)
                ->where('stats.percentageGroups.>=80.data.0.name', 'Thrall')
                ->missing('stats.percentageGroups.50-80')
                ->where('stats.totalPlayers', 1)
            )
        );
    }

    #[Test]
    public function invoke_classifies_dropping_off_correctly(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Vashj', 'is_main' => true]);

        // 4 older raids attended
        for ($i = 0; $i < 4; $i++) {
            $this->attend($this->makeReport($this->gameVersion, now()->subDays(20 + $i)), $character);
        }

        // 4 recent raids not attended
        for ($i = 0; $i < 4; $i++) {
            $this->makeReport($this->gameVersion, now()->subDays(1 + $i));
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.droppingOff.data', 1)
                ->where('stats.droppingOff.data.0.name', 'Vashj')
            )
        );
    }

    #[Test]
    public function invoke_benched_last_week_groups_characters_by_tag(): void
    {
        $kael = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Kael', 'is_main' => true]);
        $illidan = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Illidan', 'is_main' => true]);

        $monday = $this->makeTag($this->gameVersion, attributes: ['name' => 'Monday']);
        $tuesday = $this->makeTag($this->gameVersion, attributes: ['name' => 'Tuesday']);

        $this->attend($this->makeReport($this->gameVersion, now()->subDays(2), $monday), $kael, Presence::Benched);
        $this->attend($this->makeReport($this->gameVersion, now()->subDays(1), $tuesday), $illidan, Presence::Benched);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.benchedLastWeek.Monday', 1)
                ->where('stats.benchedLastWeek.Monday.0.character.name', 'Kael')
                ->has('stats.benchedLastWeek.Tuesday', 1)
                ->where('stats.benchedLastWeek.Tuesday.0.character.name', 'Illidan')
            )
        );
    }

    #[Test]
    public function invoke_benched_last_week_excludes_character_from_older_report(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Illidan', 'is_main' => true]);
        $this->attend($this->makeReport($this->gameVersion, now()->subDays(10)), $character, Presence::Benched);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.benchedLastWeek', [])
            )
        );
    }

    #[Test]
    public function stats_upcoming_absences_include_character_playable_class(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());
        $user = User::factory()->officer()->create();

        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->withPlayableClass($playableClass)->create(['name' => 'Thrall']);
        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('stats.upcomingAbsences.data.0.character')
                    ->where('stats.upcomingAbsences.data.0.character.playable_class.id', 1)
                    ->where('stats.upcomingAbsences.data.0.character.playable_class.name', 'Warrior')
                )
            );
    }

    #[Test]
    public function stats_upcoming_absences_include_null_playable_class_when_character_has_none(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());
        $user = User::factory()->officer()->create();

        $character = $this->makeCharacter($this->gameVersion, null, ['name' => 'Thrall']);
        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('stats.upcomingAbsences.data.0.character')
                    ->where('stats.upcomingAbsences.data.0.character.playable_class', null)
                )
            );
    }

    #[Test]
    public function invoke_classifies_picking_up_correctly(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Maiev', 'is_main' => true]);

        $this->attend($this->makeReport($this->gameVersion, now()->subDays(30)), $character);

        for ($i = 0; $i < 3; $i++) {
            $this->makeReport($this->gameVersion, now()->subDays(15 + $i));
        }

        for ($i = 0; $i < 4; $i++) {
            $this->attend($this->makeReport($this->gameVersion, now()->subDays(1 + $i)), $character);
        }

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('stats.pickingUp.data', 1)
                ->where('stats.pickingUp.data.0.name', 'Maiev')
            )
        );
    }

    #[Test]
    public function invoke_phase_attendance_null_when_no_phase_started(): void
    {
        $this->makeReport($this->gameVersion, now()->subDay());
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
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);

        Phase::factory()->forGameVersion($this->gameVersion)->create(['start_date' => now()->subDays(14)]);

        $this->attend($this->makeReport($this->gameVersion, now()->subDays(20)), $character);
        $this->attend($this->makeReport($this->gameVersion, now()->subDays(10)), $character);
        $this->makeReport($this->gameVersion, now()->subDays(5));

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.phaseAttendance', 50)
            )
        );
    }

    #[Test]
    public function invoke_phase_attendance_excludes_another_versions_reports_and_phases(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['start_date' => now()->subDays(14)]);
        $this->attend($this->makeReport($this->gameVersion, now()->subDays(10)), $character);
        $other = $this->makeAttendanceVersion();
        Phase::factory()->forGameVersion($other)->create(['start_date' => now()->subDays(3)]);
        $this->makeReport($other, now()->subDays(2));

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.phaseAttendance', 100)
                    ->where('stats.previousPhaseAttendance', null)));
    }

    #[Test]
    public function invoke_total_players_breakdown_counts_mains_and_linked_separately(): void
    {
        $main1 = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $main2 = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $linked = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => false]);

        $report = $this->makeReport($this->gameVersion, now()->subDays(1));
        $this->attend($report, $main1);
        $this->attend($report, $main2);
        $this->attend($report, $linked);

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
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Vol\'jin', 'is_main' => true]);

        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => now()->subDays(30)]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => now()->subDays(7)]);

        $this->attend($this->makeReport($this->gameVersion, now()->subDays(20)), $character);
        $this->makeReport($this->gameVersion, now()->subDays(3));

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('stats.previousPhaseAttendance', 100)
                ->where('stats.phaseAttendance', null)
            )
        );
    }

    #[Test]
    public function invoke_phase_attendance_counts_the_launch_day_raid_in_the_current_phase_only(): void
    {
        $this->freezeTime();
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => '2026-09-01 00:00:00']);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => '2026-10-01 00:00:00']);

        $this->attend($this->makeReport($this->gameVersion, '2026-10-01 20:00'), $character);
        $this->makeReport($this->gameVersion, '2026-09-30 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.phaseAttendance', 100)));
    }

    #[Test]
    public function invoke_previous_phase_attendance_counts_the_day_before_launch_but_not_the_launch_day(): void
    {
        $this->freezeTime();
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 1, 'start_date' => '2026-09-01 00:00:00']);
        Phase::factory()->forGameVersion($this->gameVersion)->create(['number' => 2, 'start_date' => '2026-10-01 00:00:00']);

        $this->attend($this->makeReport($this->gameVersion, '2026-09-30 20:00'), $character);
        $this->makeReport($this->gameVersion, '2026-10-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.previousPhaseAttendance', 100)));
    }

    #[Test]
    public function invoke_character_lists_carry_only_name_class_and_scalars(): void
    {
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true, 'name' => 'Vashj']);
        $this->attend($this->makeReport($this->gameVersion, now()->subDay()), $character);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.percentageGroups.>=80.data.0.name', 'Vashj')
                    ->where('stats.percentageGroups.>=80.data.0.playable_class', null)
                    ->missing('stats.percentageGroups.>=80.data.0.rank')));
    }

    #[Test]
    public function invoke_renders_empty_stats_without_a_game_version(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('gameVersion', null)
                ->where('latestReportDate', null)
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('stats.totalPlayers', 0)
                    ->has('stats.droppingOff.data', 0)
                    ->has('stats.upcomingAbsences.data', 0)));
    }

    #[Test]
    public function invoke_renders_zero_totals_when_the_version_has_no_counting_ranks(): void
    {
        $this->rank->update(['count_attendance' => false]);
        $this->attend($this->makeReport($this->gameVersion, now()->subDay()), $this->makeCharacter($this->gameVersion, $this->rank));

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('stats.totalPlayers', 0)));
    }

    #[Test]
    public function invoke_uses_the_session_game_version(): void
    {
        $newer = $this->makeAttendanceVersion(['release_date' => '2024-06-01']);
        $this->makeReport($newer, now()->subDay());
        $this->makeReport($this->gameVersion, now()->subDay());

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $this->gameVersion->slug])
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', $this->gameVersion->slug));
    }

    #[Test]
    public function invoke_payload_query_count_does_not_grow_with_rows(): void
    {
        $report = $this->makeReport($this->gameVersion, now()->subDay());
        $this->attend($report, $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $user = User::factory()->officer()->create();
        $this->actingAs($user)->get(route('raiding.attendance.dashboard'));
        Cache::tags(['attendance'])->flush();
        $oneRow = $this->countDeferredQueries($user, 'raiding.attendance.dashboard', 'stats');

        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        }
        Cache::tags(['attendance'])->flush();

        $this->assertSame($oneRow, $this->countDeferredQueries($user, 'raiding.attendance.dashboard', 'stats'));
    }

    // ==================== helpers ====================

    private function countDeferredQueries(User $user, string $routeName, string $prop): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route($routeName))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->has($prop)));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
