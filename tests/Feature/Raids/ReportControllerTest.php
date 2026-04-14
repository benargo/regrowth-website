<?php

namespace Tests\Feature\Raids;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags(['warcraftlogs'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    // ==================== Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('raids.reports.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_forbids_loot_councillor_users(): void
    {
        $user = User::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function index_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertOk();
    }

    // ==================== Deferred Prop ====================

    #[Test]
    public function reports_prop_is_deferred_and_not_in_initial_response(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Reports/Index')
            ->missing('reports')
        );
    }

    #[Test]
    public function reports_deferred_prop_returns_paginated_data(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Reports/Index')
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
    public function reports_deferred_prop_returns_empty_when_no_data(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Reports/Index')
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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

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
        Report::factory()->withGuildTag($tag)->create(['title' => 'Zone A Raid', 'zone_id' => 1, 'zone_name' => 'Zone A', 'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        Report::factory()->withGuildTag($tag)->create(['title' => 'Zone B Raid', 'zone_id' => 2, 'zone_name' => 'Zone B', 'start_time' => Carbon::parse('2025-01-08 20:00', 'UTC')]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['zone_ids' => '1']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['guild_tag_ids' => (string) $tag1->id]));

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
        $response = $this->actingAs($user)->get(route('raids.reports.index', ['days' => '1']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['since_date' => '2025-01-15']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['before_date' => '2025-01-15']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['zone_ids' => 'none']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['guild_tag_ids' => 'none']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['days' => 'none']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['zone_ids' => 'all']));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertSessionDoesntHaveErrors(['zone_ids', 'guild_tag_ids', 'days', 'since_date', 'before_date']);
    }

    #[Test]
    public function index_rejects_zone_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['zone_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['zone_ids']);
    }

    #[Test]
    public function index_rejects_guild_tag_ids_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['guild_tag_ids' => 'not-valid']));

        $response->assertSessionHasErrors(['guild_tag_ids']);
    }

    #[Test]
    public function index_rejects_days_with_invalid_format(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['days' => 'not-valid']));

        $response->assertSessionHasErrors(['days']);
    }

    #[Test]
    public function index_rejects_days_outside_valid_range(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['days' => '7']));

        $response->assertSessionHasErrors(['days']);
    }

    #[Test]
    public function index_accepts_valid_days_range(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['days' => '0,1,2,3,4,5,6']));

        $response->assertSessionDoesntHaveErrors(['days']);
    }

    #[Test]
    public function index_rejects_invalid_since_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['since_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function index_rejects_since_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.reports.index', ['since_date' => $tomorrow]));

        $response->assertSessionHasErrors(['since_date']);
    }

    #[Test]
    public function index_rejects_invalid_before_date(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index', ['before_date' => 'not-a-date']));

        $response->assertSessionHasErrors(['before_date']);
    }

    #[Test]
    public function index_rejects_before_date_in_the_future(): void
    {
        $user = User::factory()->officer()->create();

        $tomorrow = Carbon::tomorrow(config('app.timezone'))->toDateString();
        $response = $this->actingAs($user)->get(route('raids.reports.index', ['before_date' => $tomorrow]));

        $response->assertSessionHasErrors(['before_date']);
    }

    // ==================== Earliest Date Prop ====================

    #[Test]
    public function earliest_date_is_null_when_no_reports_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

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

        $response = $this->actingAs($user)->get(route('raids.reports.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('earliestDate', '2025-01-04')
        );
    }

    // ==================== Show: Access Control ====================

    #[Test]
    public function show_requires_authentication(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $response = $this->get(route('raids.reports.show', $report));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function show_forbids_users_without_view_reports_permission(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertForbidden();
    }

    #[Test]
    public function show_allows_officers(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertOk();
    }

    // ==================== Show: Page Props ====================

    #[Test]
    public function show_renders_correct_inertia_component(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Reports/Show')
        );
    }

    #[Test]
    public function show_includes_expected_report_fields(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->withZone(1000, 'Karazhan')->create([
            'title' => 'Sunday Raid',
            'start_time' => Carbon::parse('2025-01-05 20:00', 'UTC'),
            'end_time' => Carbon::parse('2025-01-05 23:30', 'UTC'),
        ]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.id')
            ->has('report.data.code')
            ->has('report.data.title')
            ->has('report.data.start_time')
            ->has('report.data.end_time')
            ->has('report.data.zone')
            ->has('report.data.zone.id')
            ->has('report.data.zone.name')
            ->has('report.data.guild_tag')
            ->has('report.data.characters')
            ->has('report.data.linked_reports')
            ->where('report.data.title', 'Sunday Raid')
            ->where('report.data.zone.name', 'Karazhan')
        );
    }

    #[Test]
    public function show_includes_characters_with_pivot(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create(['name' => 'Thrall']);
        $report->characters()->attach($character->id, ['presence' => 75]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.characters', 1)
            ->where('report.data.characters.0.name', 'Thrall')
            ->where('report.data.characters.0.pivot.presence', 75)
        );
    }

    #[Test]
    public function show_includes_linked_reports(): void
    {
        $report1 = Report::factory()->withoutGuildTag()->create(['title' => 'Main Report']);
        $report2 = Report::factory()->withoutGuildTag()->create(['title' => 'Linked Report']);
        $report1->linkedReports()->attach($report2->id, ['created_by' => null]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report1));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.linked_reports', 1)
            ->where('report.data.linked_reports.0.title', 'Linked Report')
        );
    }

    #[Test]
    public function show_returns_404_for_nonexistent_report(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', 'nonexistent-code'));

        $response->assertNotFound();
    }

    // ==================== Show: canManageLinks ====================

    #[Test]
    public function show_can_manage_links_is_true_for_users_with_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-reports', 'guard_name' => 'web']));
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManageLinks', true)
        );
    }

    #[Test]
    public function show_can_manage_links_is_false_for_users_without_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManageLinks', false)
        );
    }

    // ==================== Show: nearbyReports optional prop ====================

    #[Test]
    public function show_nearby_reports_is_absent_on_initial_load(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('nearbyReports')
        );
    }

    #[Test]
    public function show_nearby_reports_is_returned_on_partial_reload(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-06-01 20:00', 'UTC')]);
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raids.reports.show', $report))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data')
                    ->has('nearbyReports.meta')
                    ->has('nearbyReports.meta.current_page')
                    ->has('nearbyReports.meta.per_page')
                    ->has('nearbyReports.meta.total')
                    ->has('nearbyReports.meta.last_page')
                )
            );
    }

    #[Test]
    public function show_nearby_reports_page_one_centres_on_current_report(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();

        // Create 20 reports. Current (index 9, 0-based) has 10 newer reports (days 10-19).
        // newerCount=10, page1Offset=3, so page 1 returns 15 items.
        $reports = collect();
        for ($i = 0; $i < 20; $i++) {
            $reports->push(Report::factory()->withGuildTag($tag)->create([
                'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')->addDays($i),
            ]));
        }

        $current = $reports->get(9);
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raids.reports.show', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 15)
                    ->where('nearbyReports.meta.current_page', 1)
                )
            );
    }

    #[Test]
    public function show_nearby_reports_handles_current_report_being_newest(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();

        for ($i = 0; $i < 5; $i++) {
            Report::factory()->withGuildTag($tag)->create([
                'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')->addDays($i),
            ]);
        }

        $current = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-10 20:00', 'UTC'),
        ]);

        $user = User::factory()->officer()->create();

        // newerCount=0, page1Offset=0, returns all 6 reports
        $this->actingAs($user)
            ->get(route('raids.reports.show', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 6)
                )
            );
    }

    #[Test]
    public function show_nearby_reports_handles_current_report_being_oldest(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();

        $current = Report::factory()->withGuildTag($tag)->create([
            'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Report::factory()->withGuildTag($tag)->create([
                'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')->addDays($i),
            ]);
        }

        $user = User::factory()->officer()->create();

        // newerCount=5, page1Offset=max(0,5-7)=0, returns all 6 reports
        $this->actingAs($user)
            ->get(route('raids.reports.show', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 6)
                )
            );
    }

    // ==================== storeLinks ====================

    private function grantManageReports(): void
    {
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-reports', 'guard_name' => 'web']));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function store_links_requires_authentication(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $response = $this->post(route('raids.reports.store-links', $report), ['codes' => []]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function store_links_returns_forbidden_without_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$other->code],
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function store_links_rejects_empty_codes(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [],
        ]);

        $response->assertSessionHasErrors(['codes']);
    }

    #[Test]
    public function store_links_rejects_missing_codes(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), []);

        $response->assertSessionHasErrors(['codes']);
    }

    #[Test]
    public function store_links_rejects_nonexistent_report_code(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => ['does-not-exist'],
        ]);

        $response->assertSessionHasErrors(['codes.0']);
    }

    #[Test]
    public function store_links_rejects_current_report_in_codes(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$report->code],
        ]);

        $response->assertSessionHasErrors(['codes.0']);
    }

    #[Test]
    public function store_links_creates_bidirectional_link_between_current_and_selected(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$other->code],
        ]);

        // Forward direction: report → other
        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $report->id,
            'report_2' => $other->id,
        ]);

        // Reverse direction: other → report
        $this->assertDatabaseHas('raid_report_links', [
            'report_1' => $other->id,
            'report_2' => $report->id,
        ]);
    }

    #[Test]
    public function store_links_creates_all_combinations_when_multiple_reports_selected(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $reportA = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$reportA->code, $reportB->code],
        ]);

        // All 6 directed pairs should exist
        foreach ([
            [$report->id, $reportA->id],
            [$reportA->id, $report->id],
            [$report->id, $reportB->id],
            [$reportB->id, $report->id],
            [$reportA->id, $reportB->id],
            [$reportB->id, $reportA->id],
        ] as [$r1, $r2]) {
            $this->assertDatabaseHas('raid_report_links', ['report_1' => $r1, 'report_2' => $r2]);
        }
    }

    #[Test]
    public function store_links_is_idempotent_for_already_linked_reports(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $report->linkedReports()->attach($other->id, ['created_by' => null]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$other->code],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('raid_report_links', 2); // forward + reverse, no duplicates
    }

    #[Test]
    public function store_links_flushes_warcraftlogs_cache(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        Cache::tags('warcraftlogs')->put('some_key', 'some_value', 3600);

        $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$other->code],
        ]);

        $this->assertNull(Cache::tags('warcraftlogs')->get('some_key'));
    }

    #[Test]
    public function store_links_redirects_back_on_success(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('raids.reports.store-links', $report), [
            'codes' => [$other->code],
        ]);

        $response->assertRedirect();
    }

    // ==================== destroyLinks ====================

    #[Test]
    public function destroy_links_requires_authentication(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $response = $this->patch(route('raids.reports.destroy-links', $report));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function destroy_links_returns_forbidden_without_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        $response->assertForbidden();
    }

    #[Test]
    public function destroy_links_deletes_all_manual_links_bidirectionally(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $reportB = Report::factory()->withoutGuildTag()->create();
        $reportC = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        // Create manual bidirectional links: report ↔ B, report ↔ C, B ↔ C
        $report->linkedReports()->attach($reportB->id, ['created_by' => $user->id]);
        $reportB->linkedReports()->attach($report->id, ['created_by' => $user->id]);
        $report->linkedReports()->attach($reportC->id, ['created_by' => $user->id]);
        $reportC->linkedReports()->attach($report->id, ['created_by' => $user->id]);

        $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        // Forward links from report should be gone
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $report->id, 'report_2' => $reportB->id]);
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $report->id, 'report_2' => $reportC->id]);
        // Reverse links back to report should be gone
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $reportB->id, 'report_2' => $report->id]);
        $this->assertDatabaseMissing('raid_report_links', ['report_1' => $reportC->id, 'report_2' => $report->id]);
    }

    #[Test]
    public function destroy_links_does_not_delete_auto_linked_reports(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $autoLinked = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $report->linkedReports()->attach($autoLinked->id, ['created_by' => null]);
        $autoLinked->linkedReports()->attach($report->id, ['created_by' => null]);

        $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        $this->assertDatabaseHas('raid_report_links', ['report_1' => $report->id, 'report_2' => $autoLinked->id]);
        $this->assertDatabaseHas('raid_report_links', ['report_1' => $autoLinked->id, 'report_2' => $report->id]);
    }

    #[Test]
    public function destroy_links_is_a_no_op_when_no_manual_links_exist(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        $response->assertRedirect();
        $this->assertDatabaseCount('raid_report_links', 0);
    }

    #[Test]
    public function destroy_links_flushes_warcraftlogs_cache(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $report->linkedReports()->attach($other->id, ['created_by' => $user->id]);
        Cache::tags('warcraftlogs')->put('some_key', 'some_value', 3600);

        $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        $this->assertNull(Cache::tags('warcraftlogs')->get('some_key'));
    }

    #[Test]
    public function destroy_links_redirects_back_on_success(): void
    {
        $this->grantManageReports();
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->patch(route('raids.reports.destroy-links', $report));

        $response->assertRedirect();
    }

    // ==================== Show: impactedReports optional prop ====================

    #[Test]
    public function show_impacted_reports_is_absent_on_initial_load(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('impactedReports')
        );
    }

    #[Test]
    public function show_impacted_reports_returns_manually_linked_reports_on_reload(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $other = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $report->linkedReports()->attach($other->id, ['created_by' => $user->id]);

        $this->actingAs($user)
            ->get(route('raids.reports.show', $report))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('impactedReports', fn (Assert $reload) => $reload
                    ->has('impactedReports.data', 1)
                    ->where('impactedReports.data.0.code', $other->code)
                )
            );
    }

    #[Test]
    public function show_impacted_reports_excludes_auto_linked_reports_on_reload(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $autoLinked = Report::factory()->withoutGuildTag()->create();
        $manualLinked = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $report->linkedReports()->attach($autoLinked->id, ['created_by' => null]);
        $report->linkedReports()->attach($manualLinked->id, ['created_by' => $user->id]);

        $this->actingAs($user)
            ->get(route('raids.reports.show', $report))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('impactedReports', fn (Assert $reload) => $reload
                    ->has('impactedReports.data', 1)
                    ->where('impactedReports.data.0.code', $manualLinked->code)
                )
            );
    }
}
