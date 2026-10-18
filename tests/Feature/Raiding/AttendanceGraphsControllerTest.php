<?php

namespace Tests\Feature\Raiding;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Enums\Presence;
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
class AttendanceGraphsControllerTest extends TestCase
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

        $this->gameVersion = $this->createAttendanceVersion();
        $this->rank = $this->createRank($this->gameVersion);
    }

    // ==================== invoke: Access Control ====================

    #[Test]
    public function invoke_requires_authentication(): void
    {
        $response = $this->get(route('raiding.attendance.graphs.index'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertOk();
    }

    // ==================== invoke: scatterPoints ====================

    #[Test]
    public function invoke_scatter_points_prop_is_deferred_and_not_in_initial_response(): void
    {
        $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasAttendance', true)
            ->missing('scatterPoints')
        );
    }

    #[Test]
    public function invoke_scatter_points_returns_one_entry_per_player_with_expected_keys(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Illidan', 'is_main' => true]);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.graphs.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('scatterPoints', 1)
                ->where('scatterPoints.0.character.id', $character->id)
                ->where('scatterPoints.0.character.name', 'Illidan')
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
    public function invoke_scatter_points_counts_benched_and_other_absences(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Maiev', 'is_main' => true]);
        $tag = $this->createTag($this->gameVersion);

        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00', $tag), $character);
        $this->attend($this->createReport($this->gameVersion, '2025-01-02 20:00', $tag), $character, Presence::Benched);
        $this->attend($this->createReport($this->gameVersion, '2025-01-03 20:00', $tag), $character, Presence::Absent);

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
    public function invoke_scatter_points_counts_planned_absences_covering_raids(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Tyrande', 'is_main' => true]);
        $tag = $this->createTag($this->gameVersion);

        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00', $tag), $character);
        $this->attend($this->createReport($this->gameVersion, '2025-01-08 20:00', $tag), $character, Presence::Absent);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-07',
            'end_date' => '2025-01-09',
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
    public function invoke_scatter_points_include_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true, 'playable_class_id' => $playableClass->id]);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raiding.attendance.graphs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('scatterPoints.0.character.playable_class')
                    ->where('scatterPoints.0.character.playable_class.id', 1)
                    ->where('scatterPoints.0.character.playable_class.name', 'Warrior')
                )
            );
    }

    #[Test]
    public function invoke_scatter_character_carries_an_unwrapped_playable_class(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank, ['playable_class_id' => PlayableClass::factory()->create(['name' => 'Hunter'])->id]);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.graphs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('scatterPoints.0.character.playable_class.name', 'Hunter')
                    ->has('scatterPoints.0.character.playable_class.icon_url')
                    ->missing('scatterPoints.0.character.rank')));
    }

    #[Test]
    public function invoke_shows_the_empty_state_for_a_version_without_counted_attendance(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.graphs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', false)
                ->where('gameVersion.data.slug', $this->gameVersion->slug)
                ->missing('scatterPoints'));
    }

    #[Test]
    public function invoke_shows_the_empty_state_without_a_game_version(): void
    {
        $this->gameVersion->delete();

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.graphs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', false)
                ->where('gameVersion', null)
                ->missing('scatterPoints'));
    }

    #[Test]
    public function invoke_follows_the_session_version(): void
    {
        $newer = $this->createAttendanceVersion(['release_date' => '2024-06-01']);
        $this->createReport($newer, '2025-01-01 20:00');
        $this->createReport($this->gameVersion, '2025-01-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $this->gameVersion->slug])
            ->get(route('raiding.attendance.graphs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasAttendance', true)
                ->where('gameVersion.data.slug', $this->gameVersion->slug));
    }

    #[Test]
    public function invoke_payload_query_count_does_not_grow_with_rows(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank));
        $user = User::factory()->officer()->create();
        $this->countDeferredQueries($user, 'raiding.attendance.graphs.index', 'scatterPoints');
        Cache::tags(['attendance'])->flush();
        $oneRow = $this->countDeferredQueries($user, 'raiding.attendance.graphs.index', 'scatterPoints');

        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank));
        }
        Cache::tags(['attendance'])->flush();

        $this->assertSame($oneRow, $this->countDeferredQueries($user, 'raiding.attendance.graphs.index', 'scatterPoints'));
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
