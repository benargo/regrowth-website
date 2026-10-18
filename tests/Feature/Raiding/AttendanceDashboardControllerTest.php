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

        $this->freezeTime();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags(['attendance', 'reports'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-attendance', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);

        $this->gameVersion = $this->createAttendanceVersion();
        $this->rank = $this->createRank($this->gameVersion);
    }

    // ==================== invoke: access ====================

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

    // ==================== invoke: props ====================

    #[Test]
    public function invoke_returns_latest_report_date(): void
    {
        $this->createReport($this->gameVersion, '2025-03-07 12:00:00');
        $this->createReport($this->gameVersion, '2025-03-15 12:00:00');
        $this->createReport($this->gameVersion, '2025-03-10 12:00:00');

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('latestReportDate', '2025-03-15')
        );
    }

    #[Test]
    public function invoke_stats_prop_is_deferred_and_not_in_initial_response(): void
    {
        $this->createReport($this->gameVersion, now()->subDay());
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasAttendance', true)
            ->missing('stats')
        );
    }

    #[Test]
    public function invoke_deferred_stats_contains_expected_keys(): void
    {
        $this->createReport($this->gameVersion, now()->subDay());
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
    public function invoke_serializes_the_stats_character_lists_with_only_their_playable_class(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true, 'name' => 'Vashj']);
        $this->attend($this->createReport($this->gameVersion, now()->subDay()), $character);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('stats.percentageGroups.>=80', 1)
                    ->where('stats.percentageGroups.>=80.0.name', 'Vashj')
                    ->where('stats.percentageGroups.>=80.0.playable_class', null)
                    ->missing('stats.percentageGroups.>=80.0.rank')
                    ->missing('stats.percentageGroups.50-80')
                    ->has('stats.droppingOff', 0)
                    ->has('stats.pickingUp', 0)));
    }

    #[Test]
    public function invoke_serializes_the_stats_upcoming_absences_with_their_reason_and_character(): void
    {
        $this->createReport($this->gameVersion, now()->subDay());
        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->withPlayableClass($playableClass)->create(['name' => 'Thrall']);
        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => now()->addDay(),
            'reason' => PlannedAbsenceReason::Holiday,
        ]);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('stats.upcomingAbsences', 1)
                    ->where('stats.upcomingAbsences.0.character.name', 'Thrall')
                    ->where('stats.upcomingAbsences.0.character.playable_class.name', 'Warrior')
                    ->where('stats.upcomingAbsences.0.reason.key', 'On holiday')
                    ->where('stats.upcomingAbsences.0.reason.icon', 'umbrella-beach')));
    }

    #[Test]
    public function invoke_serializes_the_stats_benched_characters_by_tag(): void
    {
        $kael = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Kael', 'is_main' => true]);
        $monday = $this->createTag($this->gameVersion, attributes: ['name' => 'Monday']);
        $this->attend($this->createReport($this->gameVersion, now()->subDays(2), $monday), $kael, Presence::Benched);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('stats.benchedLastWeek.Monday', 1)
                    ->where('stats.benchedLastWeek.Monday.0.character.name', 'Kael')));
    }

    #[Test]
    public function invoke_shows_the_empty_state_for_a_version_without_counted_attendance(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', false)
                ->where('gameVersion.data.slug', $this->gameVersion->slug)
                ->missing('latestReportDate')
                ->missing('stats'));
    }

    #[Test]
    public function invoke_shows_the_empty_state_without_a_game_version(): void
    {
        $this->gameVersion->delete();

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', false)
                ->where('gameVersion', null)
                ->missing('stats'));
    }

    #[Test]
    public function invoke_follows_the_session_version(): void
    {
        $newer = $this->createAttendanceVersion(['release_date' => '2024-06-01']);
        $this->createReport($newer, now()->subDay());
        $this->createReport($this->gameVersion, now()->subDay());

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $this->gameVersion->slug])
            ->get(route('raiding.attendance.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', true)
                ->where('gameVersion.data.slug', $this->gameVersion->slug));
    }

    #[Test]
    public function invoke_payload_query_count_does_not_grow_with_rows(): void
    {
        $report = $this->createReport($this->gameVersion, now()->subDay());
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $user = User::factory()->officer()->create();
        $this->actingAs($user)->get(route('raiding.attendance.dashboard'));
        Cache::tags(['attendance'])->flush();
        $oneRow = $this->countDeferredQueries($user, 'raiding.attendance.dashboard', 'stats');

        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
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
