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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CreateTest extends TestCase
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
    public function create_requires_authentication(): void
    {
        $response = $this->get(route('raiding.reports.create'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function create_forbids_users_without_manage_reports(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function create_allows_users_with_manage_reports(): void
    {
        $this->grantManageReports();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.create'));

        $response->assertOk();
    }

    // ==================== Page Props ====================

    #[Test]
    public function create_renders_correct_inertia_component(): void
    {
        $this->grantManageReports();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Reports/Create')
        );
    }

    #[Test]
    public function create_includes_expected_props(): void
    {
        $this->grantManageReports();
        Zone::factory()->create();
        GuildTag::factory()->withoutPhase()->create();
        Character::factory()->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('expansions')
            ->has('expansions.0.id')
            ->has('expansions.0.name')
            ->has('expansions.0.zones')
            ->has('guildTags')
            ->has('characters')
            ->has('defaultExpansionId')
        );
    }

    #[Test]
    public function create_nearby_reports_absent_on_initial_load(): void
    {
        $this->grantManageReports();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raiding.reports.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('nearbyReports')
        );
    }

    // ==================== lootCouncillorCandidates optional ====================

    #[Test]
    public function create_loot_councillor_candidates_returned_on_partial_reload(): void
    {
        $this->grantManageReports();
        $user = User::factory()->officer()->create();
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);

        $this->actingAs($user)
            ->get(route('raiding.reports.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('lootCouncillorCandidates', fn (Assert $reload) => $reload
                    ->has('lootCouncillorCandidates', 1)
                    ->where('lootCouncillorCandidates.0.name', 'Alice')
                )
            );
    }

    // ==================== nearbyReports optional (union-find) ====================

    #[Test]
    public function create_nearby_reports_path_compresses_union_find_tree_for_deep_link_chains(): void
    {
        $this->grantManageReports();
        Cache::tags(['raids', 'warcraftlogs'])->flush();

        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();

        $r1 = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 19:00', 'UTC')]);
        $r2 = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 19:30', 'UTC')]);
        $r3 = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $r4 = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:30', 'UTC')]);

        // R1↔R3, R2↔R4, R3↔R4 creates parent[R4]→R2→R1 after initial unions;
        // the next find(R4) traverses two levels and path compression fires.
        DB::table('raid_report_links')->insert([
            ['report_1' => $r1->id, 'report_2' => $r3->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r3->id, 'report_2' => $r1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r2->id, 'report_2' => $r4->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r4->id, 'report_2' => $r2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r3->id, 'report_2' => $r4->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $r4->id, 'report_2' => $r3->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)
            ->get(route('raiding.reports.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 1)
                )
            );
    }

    #[Test]
    public function create_nearby_reports_skips_links_referencing_nonexistent_reports(): void
    {
        $this->grantManageReports();
        Cache::tags(['raids', 'warcraftlogs'])->flush();

        $tag = GuildTag::factory()->withoutPhase()->create();
        $user = User::factory()->officer()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);

        $phantom = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:30', 'UTC')]);

        // Create a valid FK link, then prime the reports cache with only $report's data.
        // The links cache refetches from DB (both were flushed above), so the link is present
        // but $phantom's ID is absent from $parent — simulating a dangling reference.
        // buildNearbyReportClusters() must skip it via the isset() guard.
        DB::table('raid_report_links')->insert([
            ['report_1' => $report->id, 'report_2' => $phantom->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Cache::tags(['raids', 'warcraftlogs'])->put(
            'reports:select:id,start_time',
            Report::select('id', 'start_time')->where('id', $report->id)->get()->toArray(),
            now()->addMinutes(5)
        );

        $this->actingAs($user)
            ->get(route('raiding.reports.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nearbyReports', fn (Assert $reload) => $reload
                    ->has('nearbyReports.data', 1)
                )
            );
    }
}
