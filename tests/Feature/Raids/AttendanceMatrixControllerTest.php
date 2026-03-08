<?php

namespace Tests\Feature\Raids;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\Permission;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceMatrixControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags('warcraftlogs')->flush();
        Cache::tags(['attendance', 'attendance:matrix'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-attendance', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);

        // Mock external Blizzard API services used by the matrix controller action.
        $this->mock(PlayableClassService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('find')->andReturn([]);
            $mock->shouldReceive('iconUrl')->andReturn(null);
        });
        $this->mock(GuildService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('roster')->andReturn(['members' => []]);
        });
    }

    // ==================== matrix: Access Control ====================

    public function test_matrix_requires_authentication(): void
    {
        $response = $this->get(route('raids.attendance.matrix'));

        $response->assertRedirect('/login');
    }

    public function test_matrix_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    public function test_matrix_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    public function test_matrix_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    public function test_matrix_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertForbidden();
    }

    public function test_matrix_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertOk();
    }

    // ==================== matrix: Deferred Prop ====================

    public function test_matrix_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Attendance/Matrix')
            ->missing('matrix')
        );
    }

    public function test_matrix_deferred_prop_returns_raids_and_rows(): void
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

    public function test_matrix_deferred_prop_returns_empty_when_no_data(): void
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

    public function test_matrix_includes_filter_option_props(): void
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

    public function test_matrix_ranks_includes_all_ranks_regardless_of_count_attendance(): void
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

    public function test_matrix_character_filter_is_null_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character', null)
        );
    }

    public function test_matrix_character_filter_returns_character_name(): void
    {
        $character = Character::factory()->main()->create(['name' => 'Thrall']);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => $character->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.character', 'Thrall')
        );
    }

    public function test_matrix_default_guild_tag_ids_include_only_attendance_counting_tags(): void
    {
        $countingTag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        GuildTag::factory()->withoutPhase()->create(['count_attendance' => false]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.guild_tag_ids', null)
        );
    }

    // ==================== matrix: Validation ====================

    public function test_matrix_accepts_omitted_optional_fields(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertSessionDoesntHaveErrors(['character', 'zone_ids', 'guild_tag_ids', 'since_date', 'before_date']);
    }

    public function test_matrix_accepts_valid_character_id(): void
    {
        $character = Character::factory()->main()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => $character->id]));

        $response->assertSessionDoesntHaveErrors(['character']);
    }

    public function test_matrix_rejects_nonexistent_character_id(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => 99999]));

        $response->assertSessionHasErrors(['character']);
    }

    public function test_matrix_rejects_non_integer_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['character' => 'thrall']));

        $response->assertSessionHasErrors(['character']);
    }

    public function test_matrix_accepts_valid_zone_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['zone_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['zone_ids']);
    }

    public function test_matrix_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['zone_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['zone_ids']);
    }

    public function test_matrix_accepts_valid_guild_tag_ids_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => '1,2,3']));

        $response->assertSessionDoesntHaveErrors(['guild_tag_ids']);
    }

    public function test_matrix_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['guild_tag_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    public function test_matrix_accepts_valid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    public function test_matrix_rejects_invalid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['since_date']);
    }

    public function test_matrix_accepts_valid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-15']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    public function test_matrix_rejects_invalid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['before_date']);
    }

    public function test_matrix_accepts_since_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    public function test_matrix_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => $tomorrow]));

        $response->assertSessionHasErrors(['since_date']);
    }

    public function test_matrix_accepts_before_date_of_today(): void
    {
        $user = User::factory()->officer()->create();

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => $today]));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    public function test_matrix_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => $tomorrow]));

        $response->assertSessionHasErrors(['before_date']);
    }

    public function test_matrix_accepts_since_date_equal_to_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04 (one day before earliest report date in app timezone)
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['since_date']);
    }

    public function test_matrix_rejects_since_date_before_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        // Min date is 2025-01-04, so 2025-01-03 should be rejected
        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['since_date']);
    }

    public function test_matrix_accepts_before_date_equal_to_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-04']));

        $response->assertSessionDoesntHaveErrors(['before_date']);
    }

    public function test_matrix_rejects_before_date_before_minimum(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'Europe/Paris')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['before_date' => '2025-01-03']));

        $response->assertSessionHasErrors(['before_date']);
    }

    public function test_matrix_accepts_any_date_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['since_date' => '2000-01-01', 'before_date' => '2000-01-01']));

        $response->assertSessionDoesntHaveErrors(['since_date', 'before_date']);
    }

    // ==================== matrix: Earliest Date Prop ====================

    public function test_matrix_earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', null)
        );
    }

    public function test_matrix_earliest_date_is_day_before_earliest_report(): void
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

    public function test_matrix_character_filter_limits_data_to_that_character(): void
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

    public function test_matrix_guild_tag_filter_limits_data_to_selected_tag(): void
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

    public function test_matrix_since_date_filter_excludes_older_reports(): void
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

    public function test_matrix_before_date_filter_excludes_newer_reports(): void
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

    public function test_matrix_filters_include_combine_linked_characters_filter(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('filters.combine_linked_characters')
        );
    }

    public function test_matrix_combine_linked_characters_defaults_to_true_when_not_specified(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', true)
        );
    }

    public function test_matrix_combine_linked_characters_can_be_set_to_false(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['combine_linked_characters' => false]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.combine_linked_characters', false)
        );
    }

    public function test_matrix_combine_linked_characters_rejects_non_boolean(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.attendance.matrix', ['combine_linked_characters' => 'banana']));

        $response->assertSessionHasErrors(['combine_linked_characters']);
    }

    // ==================== matrix: Caching ====================

    public function test_matrix_result_is_served_from_cache_on_second_request(): void
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

    public function test_matrix_cache_is_distinct_per_filter_combination(): void
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
