<?php

namespace Tests\Feature\Raids;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Blizzard\BlizzardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceMatrixControllerTest extends TestCase
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

        // Mock external Blizzard API services used by the matrix controller action.
        $this->mock(BlizzardService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlayableClass')->andReturn([]);
            $mock->shouldReceive('getPlayableClassMedia')->andReturn(['assets' => []]);
            $mock->shouldReceive('getGuildRoster')->andReturn(['members' => []]);
        });
    }

    // ==================== matrix: Access Control ====================

    #[Test]
    public function matrix_requires_authentication(): void
    {
        $response = $this->get(route('raids.attendance.matrix'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function matrix_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Test]
    public function matrix_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Test]
    public function matrix_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Test]
    public function matrix_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    #[Test]
    public function matrix_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertOk();
    }

    // ==================== matrix: Deferred Prop ====================

    #[Test]
    public function matrix_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Attendance/Matrix')
            ->missing('matrix')
        );
    }

    #[Test]
    public function matrix_deferred_prop_returns_raids_and_rows(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Attendance/Matrix')
            ->missing('matrix')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix')
                ->has('matrix.raids', 1)
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Thrall')
                ->where('matrix.rows.0.attendance.0', 1)
            )
        );
    }

    #[Test]
    public function matrix_deferred_prop_returns_empty_when_no_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('matrix.raids', [])
                ->where('matrix.rows', [])
            )
        );
    }

    // ==================== matrix: Filter Props ====================

    #[Test]
    public function matrix_includes_filter_option_props(): void
    {
        GuildRank::factory()->create();
        GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Attendance/Matrix')
            ->has('ranks')
            ->has('zones')
            ->has('guildTags')
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
    public function matrix_ranks_includes_all_ranks_regardless_of_count_attendance(): void
    {
        $counting = GuildRank::factory()->create();
        $nonCounting = GuildRank::factory()->doesNotCountAttendance()->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('ranks', 2)
            ->where('ranks', fn ($ranks) => collect($ranks)->pluck('id')->sort()->values()->toArray() === collect([$counting->id, $nonCounting->id])->sort()->values()->toArray())
        );
    }

    #[Test]
    public function matrix_character_filter_is_null_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character', null)
        );
    }

    #[Test]
    public function matrix_character_filter_returns_character_name(): void
    {
        $character = Character::factory()->main()->create(['name' => 'Thrall']);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => $character->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character.id', $character->id)
            ->where('filters.character.name', 'Thrall')
        );
    }

    #[Test]
    public function matrix_default_guild_tag_ids_include_only_attendance_counting_tags(): void
    {
        $countingTag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        GuildTag::factory()->withoutPhase()->create(['count_attendance' => false]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.guild_tag_ids', [$countingTag->id])
        );
    }

    // ==================== matrix: Validation ====================

    #[Test]
    public function matrix_accepts_omitted_optional_fields(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertSessionDoesntHaveErrors(['character', 'zone_ids', 'guild_tag_ids', 'since_date', 'before_date']);
    }

    #[Test]
    public function matrix_accepts_valid_character_id(): void
    {
        $character = Character::factory()->main()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => $character->id]));

        $response->assertSessionDoesntHaveErrors(['character']);
    }

    #[Test]
    public function matrix_rejects_nonexistent_character_id(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => 99999]));

        $response->assertSessionHasErrors(['character']);
    }

    #[Test]
    public function matrix_rejects_non_integer_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => 'thrall']));

        $response->assertSessionHasErrors(['character']);
    }

    #[Test]
    public function matrix_accepts_valid_zone_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['zone_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['zone_ids']);
    }

    #[Test]
    public function matrix_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['zone_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['zone_ids']);
    }

    #[Test]
    public function matrix_accepts_valid_guild_tag_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['guild_tag_ids']);
    }

    #[Test]
    public function matrix_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    #[Test]
    public function matrix_accepts_valid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Test]
    public function matrix_rejects_invalid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function matrix_accepts_valid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Test]
    public function matrix_rejects_invalid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function matrix_accepts_since_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Test]
    public function matrix_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => $tomorrow]));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function matrix_accepts_before_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Test]
    public function matrix_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => $tomorrow]));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function matrix_accepts_since_date_equal_to_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04 (one day before earliest report date in app timezone)
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    #[Test]
    public function matrix_rejects_since_date_before_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04, so 2025-01-03 should be rejected
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function matrix_accepts_before_date_equal_to_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    #[Test]
    public function matrix_rejects_before_date_before_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function matrix_accepts_any_date_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2000-01-01', 'before_date' => '2000-01-01']));

        $response->assertSessionDoesntHaveErrors(['since_date', 'before_date']);
    }

    // ==================== matrix: Earliest Date Prop ====================

    #[Test]
    public function matrix_earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', null)
        );
    }

    #[Test]
    public function matrix_earliest_date_is_day_before_earliest_report(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-03-10 20:00', 'Europe/Paris')]);
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', '2025-01-04')
        );
    }

    // ==================== matrix: Server-Side Filter Behavior ====================

    #[Test]
    public function matrix_character_filter_limits_data_to_that_character(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);
        $report->characters()->attach($jaina->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => $thrall->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Thrall')
            )
        );
    }

    #[Test]
    public function matrix_guild_tag_filter_limits_data_to_selected_tag(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $tag1 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $tag2 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $report1 = Report::factory()->withGuildTag($tag1)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $report2 = Report::factory()->withGuildTag($tag2)->create(['start_time' => Carbon::parse('2025-01-08 20:00', 'Europe/Paris')]);
        $report1->characters()->attach($thrall->id, ['presence' => 1]);
        $report2->characters()->attach($jaina->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => (string) $tag1->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Thrall')
            )
        );
    }

    #[Test]
    public function matrix_since_date_filter_excludes_older_reports(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $oldReport = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $newReport = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris')]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        // since_date = 2025-01-15, which means only reports on or after Jan 15 05:00 are included
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Jaina')
            )
        );
    }

    #[Test]
    public function matrix_before_date_filter_excludes_newer_reports(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $oldReport = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'Europe/Paris')]);
        $newReport = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-02-01 20:00', 'Europe/Paris')]);
        $oldReport->characters()->attach($thrall->id, ['presence' => 1]);
        $newReport->characters()->attach($jaina->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        // before_date = 2025-01-15, which means only reports before Jan 16 05:00 are included
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Thrall')
            )
        );
    }

    // ==================== matrix: combine_linked_characters Filter ====================

    #[Test]
    public function matrix_filters_include_combine_linked_characters_filter(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('filters.combine_linked_characters')
        );
    }

    #[Test]
    public function matrix_combine_linked_characters_defaults_to_true_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', true)
        );
    }

    #[Test]
    public function matrix_combine_linked_characters_can_be_set_to_false(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['combine_linked_characters' => false]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', false)
        );
    }

    #[Test]
    public function matrix_combine_linked_characters_rejects_non_boolean(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['combine_linked_characters' => 'banana']));

        $response->assertSessionHasErrors(['combine_linked_characters']);
    }

    // ==================== matrix: Caching ====================

    #[Test]
    public function matrix_result_is_served_from_cache_on_second_request(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        $report->characters()->attach($thrall->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();
        $params = ['guild_tag_ids' => (string) $tag->id];

        // First request populates the cache.
        $this->actingAs($user)->get(route('raids.attendance.matrix', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.name', 'Thrall')
                )
            );

        // Add a new character to the DB — if caching is working this should not appear.
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);
        $report2 = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-02-01 20:00', 'UTC')]);
        $report2->characters()->attach($jaina->id, ['presence' => 1]);

        // Second request with identical filters should return the cached result.
        $this->actingAs($user)->get(route('raids.attendance.matrix', $params))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.name', 'Thrall')
                )
            );
    }

    // ==================== matrix: Zone Filter ====================

    #[Test]
    public function matrix_sorts_zone_ids_for_deterministic_caching_when_provided(): void
    {
        $user = User::factory()->officer()->create();

        // Non-null zone_ids exercises the sort($zoneIds) branch in matrixCacheKey()
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['zone_ids' => '3,1,2']));

        $response->assertOk();
        $response->assertSessionDoesntHaveErrors(['zone_ids']);
    }

    // ==================== matrix: Planned Absences ====================

    #[Test]
    public function matrix_includes_referenced_planned_absences_in_response(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $raidDate = Carbon::parse('2025-01-15 20:00', 'UTC');
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => $raidDate]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-15',
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.planned_absences', 1)
            )
        );
    }

    // ==================== matrix: Rank Filter ====================

    #[Test]
    public function matrix_excludes_mains_whose_rank_is_not_in_the_rank_filter(): void
    {
        $rank1 = GuildRank::factory()->create();
        $rank2 = GuildRank::factory()->create();
        $main1 = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank1->id]);
        $main2 = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank2->id]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($main1->id, ['presence' => 1]);
        $report->characters()->attach($main2->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', [
            'rank_ids' => (string) $rank1->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.name', 'Thrall')
            )
        );
    }

    // ==================== matrix: Zero Attendance ====================

    #[Test]
    public function matrix_records_zero_attendance_when_all_merged_characters_were_absent(): void
    {
        $rank = GuildRank::factory()->create();
        $main = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $alt = Character::factory()->create(['name' => 'ThrallAlt', 'is_main' => false, 'rank_id' => $rank->id]);

        \DB::table('character_links')->insert([
            ['character_id' => $main->id, 'linked_character_id' => $alt->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $alt->id, 'linked_character_id' => $main->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($main->id, ['presence' => 0]);
        $report->characters()->attach($alt->id, ['presence' => 0]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('matrix.rows', 1)
                ->where('matrix.rows.0.attendance.0', 0)
            )
        );
    }

    #[Test]
    public function matrix_cache_is_distinct_per_filter_combination(): void
    {
        $rank = GuildRank::factory()->create();
        $thrall = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $jaina = Character::factory()->main()->create(['name' => 'Jaina', 'rank_id' => $rank->id]);

        $tag1 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $tag2 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();

        $report1 = Report::factory()->withGuildTag($tag1)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        $report2 = Report::factory()->withGuildTag($tag2)->create(['start_time' => Carbon::parse('2025-01-08 20:00', 'UTC')]);
        $report1->characters()->attach($thrall->id, ['presence' => 1]);
        $report2->characters()->attach($jaina->id, ['presence' => 1]);

        $user = User::factory()->officer()->create();

        // Warm both caches with their respective filter sets.
        $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => (string) $tag1->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.name', 'Thrall')
                )
            );

        $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => (string) $tag2->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('matrix.rows', 1)
                    ->where('matrix.rows.0.name', 'Jaina')
                )
            );
    }
}
