<?php

namespace Tests\Feature\Reports;

use App\Models\GuildTag;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = \App\Models\Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
        $officerRole = \App\Models\DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    // ==================== Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('raiding.reports.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertOk();
    }

    // ==================== Deferred Prop ====================

    #[Test]
    public function reports_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Reports/Index')
            ->missing('reports')
        );
    }

    #[Test]
    public function reports_deferred_prop_returns_paginated_data(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Reports/Index')
            ->missing('reports')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports')
                ->has('reports.data', 1)
                ->has('reports.links')
                ->has('reports.meta.total')
            )
        );
    }

    #[Test]
    public function pagination_links_preserve_filter_query_string(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(30)->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC'),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', [
            'guild_tag_ids' => (string) $tag->id,
            'days' => '0,1,2,3,4,5,6',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports.meta.last_page', fn ($lastPage) => $lastPage > 1)
                ->where('reports.links.next', fn ($url) => str_contains($url, 'guild_tag_ids=')
                    && str_contains($url, 'days=')
                )
            )
        );
    }

    #[Test]
    public function reports_deferred_prop_returns_empty_when_no_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports.data', [])
            )
        );
    }

    // ==================== Filter Props ====================

    #[Test]
    public function index_includes_filter_option_props(): void
    {
        GuildTag::factory()->withoutPhase()->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Reports/Index')
            ->has('zones')
            ->has('guildTags')
            ->has('filters')
            ->has('filters.zone_ids')
            ->has('filters.guild_tag_ids')
            ->has('filters.days')
            ->has('filters.since_date')
            ->has('filters.before_date')
            ->has('earliestDate')
        );
    }

    // ==================== Report Shape ====================

    #[Test]
    public function reports_data_contains_expected_fields(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create([
            'title' => 'Test Raid',
            'start_time' => Carbon::parse('2025-01-06 20:00', 'UTC'),
            'end_time' => Carbon::parse('2025-01-06 23:30', 'UTC'),
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data.0.id')
                ->has('reports.data.0.code')
                ->has('reports.data.0.title')
                ->has('reports.data.0.start_time')
                ->has('reports.data.0.end_time')
                ->has('reports.data.0.zone')
                ->has('reports.data.0.zone.id')
                ->has('reports.data.0.zone.name')
                ->has('reports.data.0.guild_tag')
                ->where('reports.data.0.title', 'Test Raid')
            )
        );
    }

    // ==================== Ordering ====================

    #[Test]
    public function reports_are_ordered_by_start_time_descending(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['title' => 'Old Raid', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->create(['title' => 'New Raid', 'start_time' => Carbon::parse('2025-03-01 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports.data.0.title', 'New Raid')
                ->where('reports.data.1.title', 'Old Raid')
            )
        );
    }

    // ==================== Filter Behavior ====================

    #[Test]
    public function zone_filter_limits_results_to_matching_zone(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $zoneA = Zone::factory()->create(['id' => 1, 'name' => 'Zone A']);
        $zoneB = Zone::factory()->create(['id' => 2, 'name' => 'Zone B']);
        Report::factory()->withGuildTag($tag)->withZone($zoneA)->create(['title' => 'Zone A Raid', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->withZone($zoneB)->create(['title' => 'Zone B Raid', 'start_time' => Carbon::parse('2025-01-08 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['zone_ids' => '1']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Zone A Raid')
            )
        );
    }

    #[Test]
    public function guild_tag_filter_limits_results_to_matching_tag(): void
    {
        $tag1 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $tag2 = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag1)->create(['title' => 'Tag 1 Raid', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag2)->create(['title' => 'Tag 2 Raid', 'start_time' => Carbon::parse('2025-01-08 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['guild_tag_ids' => (string) $tag1->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Tag 1 Raid')
            )
        );
    }

    #[Test]
    public function day_filter_limits_results_to_matching_day_of_week(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        // 2025-01-06 is a Monday (Carbon day 1)
        Report::factory()->withGuildTag($tag)->create(['title' => 'Monday Raid', 'start_time' => Carbon::parse('2025-01-06 20:00', 'UTC')]);
        // 2025-01-08 is a Wednesday (Carbon day 3)
        Report::factory()->withGuildTag($tag)->create(['title' => 'Wednesday Raid', 'start_time' => Carbon::parse('2025-01-08 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        // Filter for Monday only (Carbon day 1)
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['days' => '1']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Monday Raid')
            )
        );
    }

    #[Test]
    public function since_date_filter_excludes_older_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['title' => 'Old Raid', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->create(['title' => 'New Raid', 'start_time' => Carbon::parse('2025-02-01 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['since_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'New Raid')
            )
        );
    }

    #[Test]
    public function before_date_filter_excludes_newer_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['title' => 'Old Raid', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->create(['title' => 'New Raid', 'start_time' => Carbon::parse('2025-02-01 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['before_date' => '2025-01-15']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Old Raid')
            )
        );
    }

    #[Test]
    public function zone_ids_none_returns_no_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['zone_ids' => 'none']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 0)
            )
        );
    }

    #[Test]
    public function guild_tag_ids_none_returns_no_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['guild_tag_ids' => 'none']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 0)
            )
        );
    }

    #[Test]
    public function days_none_returns_no_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['days' => 'none']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 0)
            )
        );
    }

    #[Test]
    public function zone_ids_all_returns_all_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['zone_ids' => 'all']));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 3)
            )
        );
    }

    #[Test]
    public function no_filters_returns_all_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 3)
            )
        );
    }

    // ==================== Validation ====================

    #[Test]
    public function index_accepts_omitted_optional_fields(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertSessionDoesntHaveErrors(['zone_ids', 'guild_tag_ids', 'days', 'since_date', 'before_date']);
    }

    #[Test]
    public function index_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['zone_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['zone_ids']);
    }

    #[Test]
    public function index_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['guild_tag_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    #[Test]
    public function index_rejects_days_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['days' => 'not-valid']));

        $response->assertSessionHasErrors(['days']);
    }

    #[Test]
    public function index_rejects_days_outside_valid_range(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['days' => '7']));

        $response->assertSessionHasErrors(['days']);
    }

    #[Test]
    public function index_accepts_valid_days_range(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['days' => '0,1,2,3,4,5,6']));

        $response->assertSessionDoesntHaveErrors(['days']);
    }

    #[Test]
    public function index_rejects_invalid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['since_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function index_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['since_date' => $tomorrow]));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function index_rejects_invalid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['before_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function index_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['before_date' => $tomorrow]));

        $response->assertSessionHasErrors(['before_date']);
    }

    // ==================== Earliest Date Prop ====================

    #[Test]
    public function earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', null)
        );
    }

    #[Test]
    public function earliest_date_is_day_before_earliest_report(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-03-10 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-05 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', '2025-01-04')
        );
    }
}
