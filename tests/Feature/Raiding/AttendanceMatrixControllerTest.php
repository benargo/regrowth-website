<?php

namespace Tests\Feature\Raiding;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Enums\PlannedAbsenceReason;
use App\Enums\Presence;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\User;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
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
class AttendanceMatrixControllerTest extends TestCase
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

    // ==================== matrix: Access Control ====================

    #[Test]
    public function invoke_requires_authentication(): void
    {
        $response = $this->get(route('raiding.attendance.matrix'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function invoke_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Test]
    public function invoke_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertOk();
    }

    // ==================== matrix: Deferred Prop ====================

    #[Test]
    public function invoke_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Attendance/Matrix')
            ->missing('matrix')
        );
    }

    #[Test]
    public function invoke_deferred_prop_returns_columns_and_rows(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $character = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $tag = $this->makeTag($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $this->attend($report, $character);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Attendance/Matrix')
            ->missing('matrix')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix')
                ->has('matrix.columns', 1)
                ->where('matrix.columns.0.id', (string) $report->id)
                ->missing('matrix.columns.0.code')
                ->where('matrix.columns.0.start_time', $report->start_time->setTimezone(config('app.timezone'))->toAtomString())
                ->has('matrix.columns.0.zone_name')
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
                ->where('matrix.rows.0.character.rank.name', $rank->name)
                ->missing('matrix.rows.0.character.rank.id')
                ->missing('matrix.rows.0.character.rank.sort_order')
                ->where('matrix.rows.0.attendance.0', 1)
            )
        );
    }

    #[Test]
    public function invoke_deferred_prop_rows_include_playable_class_with_icon_url(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);
        $character = $this->makeCharacter($this->gameVersion, $rank, [
            'name' => 'Thrall',
            'playable_class_id' => $playableClass->id,
            'is_main' => true,
        ]);
        $tag = $this->makeTag($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $this->attend($report, $character);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
                ->has('matrix.rows.0.character.playable_class')
                ->where('matrix.rows.0.character.playable_class.name', 'Warrior')
                ->has('matrix.rows.0.character.playable_class.icon_url')
                ->missing('matrix.rows.0.character.playable_class.slug')
            )
        );
    }

    #[Test]
    public function invoke_deferred_prop_returns_empty_when_no_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matrix.columns', [])
                ->where('matrix.rows', [])
            )
        );
    }

    // ==================== matrix: Filter Props ====================

    #[Test]
    public function invoke_includes_filter_option_props(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Attendance/Matrix')
            ->has('gameVersion.data')
            ->has('gameVersions.data', 1)
            ->has('ranks.data')
            ->has('zones.data')
            ->has('guildTags.data')
            ->has('filters')
            ->has('filters.character')
            ->has('filters.zone_ids')
            ->has('filters.guild_tag_ids')
            ->has('filters.since_date')
            ->has('filters.before_date')
            ->has('earliestDate')
        );
    }

    #[Test]
    public function invoke_ranks_includes_all_ranks_regardless_of_count_attendance(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $counting = $this->rank;
        $nonCounting = $this->makeRank($this->gameVersion, false);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('ranks.data', 2)
            ->where('ranks.data', fn ($ranks) => collect($ranks)->pluck('id')->sort()->values()->toArray() === collect([$counting->id, $nonCounting->id])->sort()->values()->toArray())
        );
    }

    #[Test]
    public function invoke_character_filter_is_null_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character', null)
        );
    }

    #[Test]
    public function invoke_character_filter_returns_character_name(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $character = $this->makeCharacter($this->gameVersion, null, ['name' => 'Thrall', 'is_main' => true]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['character' => $character->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character.id', $character->id)
            ->where('filters.character.name', 'Thrall')
        );
    }

    #[Test]
    public function invoke_character_filter_for_an_alt_returns_its_mains_combined_row(): void
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, null, ['name' => 'Go\'el', 'is_main' => false]);
        $this->linkCharacters($main, $alt);
        $this->attend($this->makeReport($this->gameVersion, '2025-01-01 20:00'), $alt);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix', ['character' => $alt->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.character', ['id' => $main->id, 'name' => 'Thrall'])
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.id', $main->id)));
    }

    #[Test]
    public function invoke_character_filter_returns_only_that_characters_row_when_not_combining(): void
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Go\'el', 'is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $main);
        $this->attend($report, $alt);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix', ['character' => $main->id, 'combine_linked_characters' => 0]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.id', $main->id)));
    }

    #[Test]
    public function invoke_filter_id_lists_are_null_by_default(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.rank_ids', null)
                ->where('filters.zone_ids', null)
                ->where('filters.guild_tag_ids', null));
    }

    // ==================== matrix: Validation ====================

    #[Test]
    public function invoke_accepts_omitted_optional_fields(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertSessionDoesntHaveErrors(['character', 'zone_ids', 'guild_tag_ids', 'since_date', 'before_date']);
    }

    #[Test]
    public function invoke_accepts_valid_character_id(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $character = $this->makeCharacter($this->gameVersion, null, ['is_main' => true]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['character' => $character->id]));

        $response->assertSessionDoesntHaveErrors(['character']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_nonexistent_character_id(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['character' => 99999]));

        $response->assertSessionHasErrors(['character']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_non_integer_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['character' => 'thrall']));

        $response->assertSessionHasErrors(['character']);
    }

    #[Test]
    public function invoke_accepts_valid_zone_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['zone_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['zone_ids']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['zone_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['zone_ids']);
    }

    #[Test]
    public function invoke_accepts_valid_guild_tag_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['guild_tag_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['guild_tag_ids']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['guild_tag_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    #[Test]
    public function invoke_accepts_valid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_invalid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function invoke_accepts_valid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_invalid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function invoke_accepts_since_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => $tomorrow]));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function invoke_accepts_before_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => $tomorrow]));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function invoke_accepts_since_date_equal_to_minimum(): void
    {
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-05 20:00', $tag);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04 (one day before earliest report date in app timezone)
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_since_date_before_minimum(): void
    {
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-05 20:00', $tag);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04, so 2025-01-03 should be rejected
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function invoke_accepts_before_date_equal_to_minimum(): void
    {
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-05 20:00', $tag);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Group('validation')]
    #[Test]
    public function invoke_rejects_before_date_before_minimum(): void
    {
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-05 20:00', $tag);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function invoke_accepts_any_date_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => '2000-01-01', 'before_date' => '2000-01-01']));

        $response->assertSessionDoesntHaveErrors(['since_date', 'before_date']);
    }

    // ==================== matrix: Earliest Date Prop ====================

    #[Test]
    public function invoke_earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', null)
        );
    }

    #[Test]
    public function invoke_earliest_date_is_day_before_earliest_report(): void
    {
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-03-10 20:00', $tag);
        $this->makeReport($this->gameVersion, '2025-01-05 20:00', $tag);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', '2025-01-04')
        );
    }

    // ==================== matrix: Server-Side Filter Behavior ====================

    #[Test]
    public function invoke_character_filter_limits_data_to_that_character(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);

        $tag = $this->makeTag($this->gameVersion);

        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $this->attend($report, $thrall);
        $this->attend($report, $jaina);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['character' => $thrall->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
            )
        );
    }

    #[Test]
    public function invoke_guild_tag_filter_limits_data_to_selected_tag(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);

        $tag1 = $this->makeTag($this->gameVersion);
        $tag2 = $this->makeTag($this->gameVersion);

        $report1 = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag1);
        $report2 = $this->makeReport($this->gameVersion, '2025-01-08 20:00', $tag2);
        $this->attend($report1, $thrall);
        $this->attend($report2, $jaina);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['guild_tag_ids' => (string) $tag1->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
            )
        );
    }

    #[Test]
    public function invoke_since_date_filter_excludes_older_reports(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);

        $tag = $this->makeTag($this->gameVersion);

        $oldReport = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $newReport = $this->makeReport($this->gameVersion, '2025-02-01 20:00', $tag);
        $this->attend($oldReport, $thrall);
        $this->attend($newReport, $jaina);

        $user = User::factory()->officer()->create();

        // since_date = 2025-01-15, which means only reports dated after 2025-01-15 are included
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['since_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Jaina')
            )
        );
    }

    #[Test]
    public function invoke_before_date_filter_excludes_newer_reports(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);

        $tag = $this->makeTag($this->gameVersion);

        $oldReport = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $newReport = $this->makeReport($this->gameVersion, '2025-02-01 20:00', $tag);
        $this->attend($oldReport, $thrall);
        $this->attend($newReport, $jaina);

        $user = User::factory()->officer()->create();

        // before_date = 2025-01-15, which means only reports dated before 2025-01-15 are included
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['before_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
            )
        );
    }

    // ==================== matrix: combine_linked_characters Filter ====================

    #[Test]
    public function invoke_filters_include_combine_linked_characters_filter(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('filters.combine_linked_characters')
        );
    }

    #[Test]
    public function invoke_combine_linked_characters_defaults_to_true_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', true)
        );
    }

    #[Test]
    public function invoke_combine_linked_characters_can_be_set_to_false(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['combine_linked_characters' => false]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', false)
        );
    }

    #[Group('validation')]
    #[Test]
    public function invoke_combine_linked_characters_rejects_non_boolean(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['combine_linked_characters' => 'banana']));

        $response->assertSessionHasErrors(['combine_linked_characters']);
    }

    // ==================== matrix: Caching ====================

    #[Test]
    public function invoke_result_is_served_from_cache_on_second_request(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $tag = $this->makeTag($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag);
        $this->attend($report, $thrall);

        $user = User::factory()->officer()->create();
        $params = ['guild_tag_ids' => (string) $tag->id];

        // First request populates the cache.
        $this->actingAs($user)->get(route('raiding.attendance.matrix', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.name', 'Thrall')
                )
            );

        // Add a new character to the DB — if caching is working this should not appear.
        // Attaching to the existing report avoids the Report observer flushing the attendance cache.
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);
        $this->attend($report, $jaina);

        // Second request with identical filters should return the cached result.
        $this->actingAs($user)->get(route('raiding.attendance.matrix', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.name', 'Thrall')
                )
            );
    }

    // ==================== matrix: Zone Filter ====================

    #[Test]
    public function invoke_sorts_zone_ids_for_deterministic_caching_when_provided(): void
    {
        $user = User::factory()->officer()->create();

        // Non-null zone_ids exercises the sort($zoneIds) branch in matrixCacheKey()
        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', ['zone_ids' => '3,1,2']));

        $response->assertOk();
        $response->assertSessionDoesntHaveErrors(['zone_ids']);
    }

    // ==================== matrix: Planned Absences ====================

    #[Test]
    public function invoke_rows_carry_their_planned_absences(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $character = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $tag = $this->makeTag($this->gameVersion);

        $raidDate = '2025-01-15 20:00';
        $report = $this->makeReport($this->gameVersion, $raidDate, $tag);
        $this->attend($report, $character);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-15',
            'reason' => PlannedAbsenceReason::Work,
            'notes' => 'Late shift.',
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->missing('matrix.planned_absences')
                ->has('matrix.rows.0.planned_absences', 1)
                ->where('matrix.rows.0.planned_absences.0', [
                    'reason' => ['label' => 'Stuck at work', 'icon' => 'briefcase'],
                    'notes' => 'Late shift.',
                ])
            )
        );
    }

    // ==================== matrix: Rank Filter ====================

    #[Test]
    public function invoke_excludes_mains_whose_rank_is_not_in_the_rank_filter(): void
    {
        $rank1 = $this->makeRank($this->gameVersion);
        $rank2 = $this->makeRank($this->gameVersion);
        $main1 = $this->makeCharacter($this->gameVersion, $rank1, ['name' => 'Thrall', 'is_main' => true]);
        $main2 = $this->makeCharacter($this->gameVersion, $rank2, ['name' => 'Jaina', 'is_main' => true]);

        $tag = $this->makeTag($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-15 20:00', $tag);
        $this->attend($report, $main1);
        $this->attend($report, $main2);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix', [
            'rank_ids' => (string) $rank1->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.character.name', 'Thrall')
            )
        );
    }

    // ==================== matrix: Zero Attendance ====================

    #[Test]
    public function invoke_records_zero_attendance_when_all_merged_characters_were_absent(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $main = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'ThrallAlt', 'is_main' => false]);
        $this->linkCharacters($main, $alt);

        $tag = $this->makeTag($this->gameVersion);
        $report = $this->makeReport($this->gameVersion, '2025-01-15 20:00', $tag);
        $this->attend($report, $main, Presence::Absent);
        $this->attend($report, $alt, Presence::Absent);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.attendance.0', 0)
            )
        );
    }

    #[Test]
    public function invoke_cache_is_distinct_per_filter_combination(): void
    {
        $rank = $this->makeRank($this->gameVersion);
        $thrall = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $rank, ['name' => 'Jaina', 'is_main' => true]);

        $tag1 = $this->makeTag($this->gameVersion);
        $tag2 = $this->makeTag($this->gameVersion);

        $report1 = $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag1);
        $report2 = $this->makeReport($this->gameVersion, '2025-01-08 20:00', $tag2);
        $this->attend($report1, $thrall);
        $this->attend($report2, $jaina);

        $user = User::factory()->officer()->create();

        // Warm both caches with their respective filter sets.
        $this->actingAs($user)->get(route('raiding.attendance.matrix', ['guild_tag_ids' => (string) $tag1->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.name', 'Thrall')
                )
            );

        $this->actingAs($user)->get(route('raiding.attendance.matrix', ['guild_tag_ids' => (string) $tag2->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.name', 'Jaina')
                )
            );
    }

    // ==================== matrix: game version ====================

    #[Test]
    public function invoke_ranks_include_only_the_selected_versions_ranks(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $this->makeRank($this->makeAttendanceVersion());

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ranks.data', 1)
                ->where('ranks.data.0.id', $this->rank->id));
    }

    #[Test]
    public function invoke_zones_and_guild_tags_list_only_those_used_by_the_versions_reports(): void
    {
        $zone = Zone::factory()->create();
        $tag = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-01 20:00', $tag, ['zone_id' => $zone->id]);
        Zone::factory()->create();
        $this->makeTag($this->gameVersion);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('zones.data', 1)
                ->where('zones.data.0.id', $zone->id)
                ->has('guildTags.data', 1)
                ->where('guildTags.data.0.id', $tag->id));
    }

    #[Test]
    public function invoke_uses_the_session_game_version(): void
    {
        $newer = $this->makeAttendanceVersion(['release_date' => '2024-06-01']);
        $this->makeReport($newer, '2025-01-01 20:00');
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $this->gameVersion->slug])
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', $this->gameVersion->slug));
    }

    #[Test]
    public function invoke_falls_back_to_the_newest_version_for_a_stale_session_slug(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => 'retired-version'])
            ->get(route('raiding.attendance.matrix'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', $this->gameVersion->slug));
    }

    #[Test]
    public function invoke_filters_to_a_character_of_the_game_version_named_in_the_query(): void
    {
        $newer = $this->makeAttendanceVersion(['release_date' => '2024-06-01']);
        $this->makeReport($newer, '2025-01-01 20:00');
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $character = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);

        $this->actingAs(User::factory()->officer()->create())
            ->withSession([ResolveCurrentGameVersion::SESSION_KEY => $newer->slug])
            ->followingRedirects()
            ->get(route('raiding.attendance.matrix', ['game_version' => $this->gameVersion->slug, 'character' => $character->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('gameVersion.data.slug', $this->gameVersion->slug)
                ->where('filters.character.id', $character->id));
    }

    #[Test]
    public function invoke_remembers_a_game_version_query_and_redirects_without_it(): void
    {
        $this->makeReport($this->gameVersion, '2025-01-01 20:00');

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix', ['game_version' => $this->gameVersion->slug, 'rank_ids' => '1']))
            ->assertRedirect(route('raiding.attendance.matrix', ['rank_ids' => '1']))
            ->assertSessionHas(ResolveCurrentGameVersion::SESSION_KEY, $this->gameVersion->slug);
    }

    #[Test]
    public function invoke_renders_empty_props_without_a_game_version(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('gameVersion', null)
                ->has('gameVersions.data', 0)
                ->has('ranks.data', 0)
                ->has('zones.data', 0)
                ->has('guildTags.data', 0)
                ->where('earliestDate', null)
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('matrix', ['columns' => [], 'rows' => []])));
    }

    #[Test]
    public function invoke_rows_carry_a_null_playable_class_for_a_character_without_one(): void
    {
        $this->attend($this->makeReport($this->gameVersion, '2025-01-01 20:00'), $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('matrix.rows.0.character.playable_class', null)));
    }

    #[Test]
    public function invoke_combining_folds_an_alt_into_its_main(): void
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, null, ['is_main' => false]);
        $this->linkCharacters($main, $alt);
        $this->attend($this->makeReport($this->gameVersion, '2025-01-01 20:00'), $alt);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.name', 'Thrall')
                    ->where('matrix.rows.0.attendance.0', 1)));
    }

    #[Test]
    public function invoke_deferred_prop_rows_omit_linked_characters_when_combining(): void
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $main);
        $this->attend($report, $alt);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.character.id', $main->id)
                    ->missing('matrix.rows.0.character.linked_characters')));
    }

    #[Test]
    public function invoke_payload_query_count_does_not_grow_with_rows(): void
    {
        $report = $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $user = User::factory()->officer()->create();
        $this->actingAs($user)->get(route('raiding.attendance.matrix'));
        Cache::tags(['attendance'])->flush();
        $oneRow = $this->countMatrixQueries($user);

        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        }
        Cache::tags(['attendance'])->flush();

        $this->assertSame($oneRow, $this->countMatrixQueries($user));
    }

    // ==================== matrix: attendance names ====================

    #[Test]
    public function invoke_sends_no_separate_attendance_names_prop(): void
    {
        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('attendanceNames')
                ->loadDeferredProps(fn (Assert $reload) => $reload->missing('attendanceNames')));
    }

    #[Test]
    public function invoke_rows_carry_each_raids_attendance_names(): void
    {
        $thrall = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $jaina = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Jaina', 'is_main' => true]);
        $earlier = $this->makeReport($this->gameVersion, '2025-01-01 20:00');
        $later = $this->makeReport($this->gameVersion, '2025-01-08 20:00');
        $this->attend($earlier, $thrall);
        $this->attend($later, $thrall, Presence::Absent);
        $this->attend($later, $jaina);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('matrix.rows.0.character.id', $jaina->id)
                    ->where('matrix.rows.0.attendance_names', [['Jaina'], []])
                    ->where('matrix.rows.1.character.id', $thrall->id)
                    ->where('matrix.rows.1.attendance_names', [[], ['Thrall']])));
    }

    #[Test]
    public function invoke_attendance_names_merge_an_alt_into_its_main(): void
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'ThrallAlt', 'is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->makeReport($this->gameVersion, '2025-01-15 20:00');
        $this->attend($report, $main);
        $this->attend($report, $alt);

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.attendance_names', [['Thrall', 'ThrallAlt']])));
    }

    #[Test]
    public function invoke_attendance_names_are_omitted_without_combining_linked_characters(): void
    {
        $this->attend(
            $this->makeReport($this->gameVersion, '2025-01-15 20:00'),
            $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall', 'is_main' => true]),
        );

        $this->actingAs(User::factory()->officer()->create())
            ->get(route('raiding.attendance.matrix', ['combine_linked_characters' => 0]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->missing('matrix.rows.0.attendance_names')));
    }

    // ==================== helpers ====================

    private function countMatrixQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('raiding.attendance.matrix'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->has('matrix')));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
