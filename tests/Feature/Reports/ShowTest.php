<?php

namespace Tests\Feature\Reports;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildTag;
use App\Models\Permission;
use App\Models\Raids\Report;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    private function grantManageReports(): void
    {
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-reports', 'guard_name' => 'web']));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ==================== Access Control ====================

    #[Test]
    public function show_requires_authentication(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();

        $response = $this->get(route('raiding.reports.show', $report));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function show_forbids_users_without_view_reports_permission(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertForbidden();
    }

    #[Test]
    public function show_allows_officers(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertOk();
    }

    // ==================== Page Props ====================

    #[Test]
    public function show_renders_correct_inertia_component(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Reports/Show')
        );
    }

    #[Test]
    public function show_includes_expected_report_fields(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();
        $zone = Zone::factory()->create(['id' => 1000, 'name' => 'Karazhan']);
        $report = Report::factory()->withGuildTag($tag)->withZone($zone)->create([
            'title' => 'Sunday Raid',
            'start_time' => Carbon::parse('2025-01-05 20:00', 'UTC'),
            'end_time' => Carbon::parse('2025-01-05 23:30', 'UTC'),
        ]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

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

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.characters', 1)
            ->where('report.data.characters.0.name', 'Thrall')
            ->where('report.data.characters.0.pivot.presence', 75)
        );
    }

    #[Test]
    public function show_returns_characters_sorted_alphabetically(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $charlie = Character::factory()->create(['name' => 'Charlie']);
        $alice = Character::factory()->create(['name' => 'Alice']);
        $bob = Character::factory()->create(['name' => 'Bob']);
        $report->characters()->attach([
            $charlie->id => ['presence' => 1],
            $alice->id => ['presence' => 1],
            $bob->id => ['presence' => 1],
        ]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.characters', 3)
            ->where('report.data.characters.0.name', 'Alice')
            ->where('report.data.characters.1.name', 'Bob')
            ->where('report.data.characters.2.name', 'Charlie')
        );
    }

    #[Test]
    public function show_includes_linked_reports(): void
    {
        $report1 = Report::factory()->withoutGuildTag()->create(['title' => 'Main Report']);
        $report2 = Report::factory()->withoutGuildTag()->create(['title' => 'Linked Report']);
        $report1->linkedReports()->attach($report2->id, ['created_by' => null]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report1));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.linked_reports', 1)
            ->where('report.data.linked_reports.0.title', 'Linked Report')
        );
    }

    #[Test]
    public function show_returns_404_for_nonexistent_report(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', 'nonexistent-code'));

        $response->assertNotFound();
    }

    #[Test]
    public function show_report_characters_include_pivot_is_loot_councillor(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $report->characters()->attach($character->id, ['presence' => 1, 'is_loot_councillor' => true]);

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('report.data.characters', 1)
            ->where('report.data.characters.0.pivot.is_loot_councillor', true)
        );
    }

    // ==================== canManageLinks ====================

    #[Test]
    public function show_can_manage_links_is_true_for_users_with_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $this->grantManageReports();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManageLinks', true)
        );
    }

    #[Test]
    public function show_can_manage_links_is_false_for_users_without_manage_reports(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManageLinks', false)
        );
    }

    // ==================== nearbyReports optional prop ====================

    #[Test]
    public function show_nearby_reports_is_absent_on_initial_load(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

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
            ->get(route('raiding.reports.show', $report))
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
    public function show_nearby_reports_paginates_clusters_at_five_per_page(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();

        // 20 unlinked reports → 20 singleton clusters → 5 per page, 4 pages.
        $reports = collect();
        for ($i = 0; $i < 20; $i++) {
            $reports->push(Report::factory()->withGuildTag($tag)->create([
                'start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')->addDays($i),
            ]));
        }

        $current = $reports->get(9);
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raiding.reports.show', $current))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 5)
                    ->where('nearbyReports.meta.current_page', 1)
                    ->where('nearbyReports.meta.per_page', 5)
                    ->where('nearbyReports.meta.total', 20)
                    ->where('nearbyReports.meta.last_page', 4)
                )
            );
    }

    #[Test]
    public function show_nearby_reports_groups_linked_reports_into_one_cluster(): void
    {
        $tag = GuildTag::factory()->withoutPhase()->create();

        $a = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-01 20:00', 'UTC')]);
        $b = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-02 20:00', 'UTC')]);
        $c = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-03 20:00', 'UTC')]);
        $d = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-04 20:00', 'UTC')]);

        // Link a ↔ b and b ↔ c → cluster {a,b,c}; d stands alone.
        DB::table('raid_report_links')->insert([
            ['report_1' => $a->id, 'report_2' => $b->id],
            ['report_1' => $b->id, 'report_2' => $a->id],
            ['report_1' => $b->id, 'report_2' => $c->id],
            ['report_1' => $c->id, 'report_2' => $b->id],
        ]);

        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get(route('raiding.reports.show', $a))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 2)
                    ->has('nearbyReports.data.0.reports', 1) // d
                    ->has('nearbyReports.data.1.reports', 3) // {a,b,c}
                    ->where('nearbyReports.meta.total', 2)
                )
            );
    }

    // ==================== impactedReports optional prop ====================

    #[Test]
    public function show_impacted_reports_is_absent_on_initial_load(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

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
            ->get(route('raiding.reports.show', $report))
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
            ->get(route('raiding.reports.show', $report))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('impactedReports', fn (Assert $reload) => $reload
                    ->has('impactedReports.data', 1)
                    ->where('impactedReports.data.0.code', $manualLinked->code)
                )
            );
    }

    // ==================== lootCouncillorCandidates optional prop ====================

    #[Test]
    public function show_loot_councillor_candidates_is_absent_on_initial_load(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.show', $report));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('lootCouncillorCandidates')
        );
    }

    #[Test]
    public function show_loot_councillor_candidates_returned_on_partial_reload(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $user = User::factory()->officer()->create();
        Character::factory()->lootCouncillor()->create();

        $this->actingAs($user)
            ->get(route('raiding.reports.show', $report))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('lootCouncillorCandidates', fn (Assert $reload) => $reload
                    ->has('lootCouncillorCandidates', 1)
                )
            );
    }
}
