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
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Access Control ====================

    #[Test]
    public function index_is_publicly_accessible(): void
    {
        $response = $this->get(route('raiding.reports.index'));

        $response->assertOk();
    }

    #[Test]
    public function index_allows_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertOk();
    }

    // ==================== Deferred Prop ====================

    #[Test]
    public function reports_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->create();

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

        $user = User::factory()->create();

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', [
            'filter' => [
                'guild_tag_ids' => (string) $tag->id,
                'days' => '0,1,2,3,4,5,6',
            ],
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports.meta.last_page', fn ($lastPage) => $lastPage > 1)
                ->where('reports.links.next', fn ($url) => str_contains($url, 'filter%5B')
                    || str_contains($url, 'filter[')
                )
            )
        );
    }

    #[Test]
    public function reports_deferred_prop_returns_empty_when_no_data(): void
    {
        $user = User::factory()->create();

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

        $user = User::factory()->create();

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data.0.id')
                ->has('reports.data.0.title')
                ->has('reports.data.0.start_time')
                ->has('reports.data.0.end_time')
                ->has('reports.data.0.duration')
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

        $user = User::factory()->create();

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['zone_ids' => '1']]));

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['guild_tag_ids' => (string) $tag1->id]]));

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

        $user = User::factory()->create();

        // Filter for Monday only (Carbon day 1)
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['days' => '1']]));

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['since_date' => '2025-01-15']]));

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['before_date' => '2025-01-15']]));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('reports.data', 1)
                ->where('reports.data.0.title', 'Old Raid')
            )
        );
    }

    #[Test]
    public function no_filters_returns_all_reports(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->count(3)->withGuildTag($tag)->create();

        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertSessionDoesntHaveErrors(['filter.zone_ids', 'filter.guild_tag_ids', 'filter.days', 'filter.since_date', 'filter.before_date']);
    }

    #[Test]
    public function index_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['zone_ids' => 'not-valid']]));

        $response->assertSessionHasErrors(['filter.zone_ids']);
    }

    #[Test]
    public function index_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['guild_tag_ids' => 'not-valid']]));

        $response->assertSessionHasErrors(['filter.guild_tag_ids']);
    }

    #[Test]
    public function index_rejects_days_with_invalid_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['days' => 'not-valid']]));

        $response->assertSessionHasErrors(['filter.days']);
    }

    #[Test]
    public function index_rejects_days_outside_valid_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['days' => '7']]));

        $response->assertSessionHasErrors(['filter.days']);
    }

    #[Test]
    public function index_accepts_valid_days_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['days' => '0,1,2,3,4,5,6']]));

        $response->assertSessionDoesntHaveErrors(['filter.days']);
    }

    #[Test]
    public function index_rejects_invalid_since_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['since_date' => 'not-a-date']]));

        $response->assertSessionHasErrors(['filter.since_date']);
    }

    #[Test]
    public function index_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['since_date' => $tomorrow]]));

        $response->assertSessionHasErrors(['filter.since_date']);
    }

    #[Test]
    public function index_rejects_invalid_before_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['before_date' => 'not-a-date']]));

        $response->assertSessionHasErrors(['filter.before_date']);
    }

    #[Test]
    public function index_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raiding.reports.index', ['filter' => ['before_date' => $tomorrow]]));

        $response->assertSessionHasErrors(['filter.before_date']);
    }

    // ==================== Earliest Date Prop ====================

    #[Test]
    public function earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->create();

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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', '2025-01-04')
        );
    }
}
