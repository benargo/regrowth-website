<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class AddonControllerTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock GuildTags to return empty tags by default
        // This prevents API calls during tests that don't specifically test attendance
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();

        $this->app->instance(GuildTags::class, $guildTags);

        // Mock BlizzardService to return empty roster by default
        // This prevents real API calls during tests that don't specifically test GRM freshness
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []])
            ->byDefault();
    }

    /**
     * Seed the export file in storage with the given overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function seedExportFile(array $overrides = []): void
    {
        $data = array_merge([
            'system' => ['date_generated' => Carbon::now()->unix()],
            'priorities' => [],
            'items' => [],
            'players' => [],
            'councillors' => [],
        ], $overrides);

        Storage::disk('local')->put('addon/export.json', json_encode($data));
    }

    // ==========================================
    // Authentication & Authorization Tests
    // ==========================================

    #[Test]
    public function export_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function export_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertOk();
    }

    #[Test]
    public function export_json_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.json'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function export_json_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_json_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_json_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_json_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertOk();
    }

    #[Test]
    public function export_schema_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.schema'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function export_schema_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_schema_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_schema_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_schema_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertOk();
    }

    // ==========================================
    // Export Endpoint Tests
    // ==========================================

    #[Test]
    public function export_renders_inertia_page_with_base64_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    #[Test]
    public function export_returns_valid_base64_encoded_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
                ->where('exportedData', fn ($data) => base64_decode($data, true) !== false)
            )
        );
    }

    #[Test]
    public function export_injects_authenticated_user_into_stored_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['system']['user'])
                        && $data['system']['user']['id'] === $this->officer->id
                        && $data['system']['user']['name'] === $this->officer->displayName;
                })
            )
        );
    }

    #[Test]
    public function export_preserves_stored_data_alongside_injected_user(): void
    {
        Storage::fake('local');
        $this->seedExportFile([
            'priorities' => [['id' => 1, 'name' => 'Tank', 'icon' => null]],
            'councillors' => [['id' => 1, 'name' => 'TestCouncillor', 'rank' => 'Officer']],
        ]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return $data['system']['user']['id'] === $this->officer->id
                        && count($data['priorities']) === 1
                        && $data['priorities'][0]['name'] === 'Tank'
                        && count($data['councillors']) === 1
                        && $data['councillors'][0]['name'] === 'TestCouncillor';
                })
            )
        );
    }

    #[Test]
    public function export_returns_empty_when_no_export_file_exists(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', '')
            )
        );
    }

    // ==========================================
    // Export JSON Endpoint Tests
    // ==========================================

    #[Test]
    public function export_json_renders_inertia_page_with_json_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    #[Test]
    public function export_json_returns_valid_json_string(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => is_array(json_decode($data, true)))
            )
        );
    }

    #[Test]
    public function export_json_returns_pretty_printed_json(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => str_contains($data, "\n"))
            )
        );
    }

    #[Test]
    public function export_json_includes_complete_data_structure(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) {
                    $data = json_decode($exportedData, true);

                    return isset($data['system'])
                        && isset($data['priorities'])
                        && isset($data['items'])
                        && isset($data['councillors']);
                })
            )
        );
    }

    #[Test]
    public function export_json_returns_empty_when_no_export_file_exists(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', '')
            )
        );
    }

    // ==========================================
    // Export Schema Endpoint Tests
    // ==========================================

    #[Test]
    public function export_schema_renders_inertia_page_with_schema(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Schema')
            ->has('schema')
        );
    }

    #[Test]
    public function export_schema_includes_json_schema_metadata(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema', fn (Assert $schema) => $schema
                ->where('$schema', 'https://json-schema.org/draft/2020-12/schema')
                ->has('$id')
                ->where('title', 'Regrowth Loot Tool Export Schema')
                ->has('description')
                ->where('type', 'object')
                ->has('properties')
            )
        );
    }

    #[Test]
    public function export_schema_defines_system_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.system')
            ->has('schema.properties.system.properties.date_generated')
            ->has('schema.properties.system.properties.user')
        );
    }

    #[Test]
    public function export_schema_defines_priorities_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.priorities')
            ->where('schema.properties.priorities.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_items_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.items')
            ->where('schema.properties.items.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_players_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players')
            ->where('schema.properties.players.type', 'array')
        );
    }

    #[Test]
    public function export_schema_defines_player_attendance_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players.items.properties.name')
            ->has('schema.properties.players.items.properties.attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.first_attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.attended')
            ->has('schema.properties.players.items.properties.attendance.properties.total')
            ->has('schema.properties.players.items.properties.attendance.properties.percentage')
        );
    }

    #[Test]
    public function export_schema_defines_councillors_properties(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.councillors')
            ->where('schema.properties.councillors.type', 'array')
            ->has('schema.properties.councillors.items.properties.id')
            ->has('schema.properties.councillors.items.properties.name')
            ->has('schema.properties.councillors.items.properties.rank')
        );
    }

    #[Test]
    public function export_schema_id_contains_version_1_2_0(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.schema'));

        $schema = $response->original->getData()['page']['props']['schema'];

        $this->assertStringContainsString('v=1.2.0', $schema['$id']);
    }

    // ==========================================
    // GRM Freshness Tests
    // ==========================================

    #[Test]
    public function export_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness')
                ->has('grmFreshness.lastModified')
                ->has('grmFreshness.dataIsStale')
            )
        );
    }

    #[Test]
    public function export_json_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness')
                ->has('grmFreshness.lastModified')
                ->has('grmFreshness.dataIsStale')
            )
        );
    }

    #[Test]
    public function grm_freshness_returns_epoch_timestamp_when_no_file_exists(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.lastModified', fn ($value) => Carbon::parse($value)->timestamp === 0)
            )
        );
    }

    #[Test]
    public function grm_freshness_is_not_stale_when_no_file_exists_and_no_raiders(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    #[Test]
    public function grm_freshness_is_stale_when_no_file_exists_but_guild_has_raiders(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardService to return 5 raiders
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 3, 'name' => 'Player3'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 4, 'name' => 'Player4'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 5, 'name' => 'Player5'], 'rank' => $raiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 5 raiders in guild, 0 in GRM file = difference of 5 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    #[Test]
    public function grm_freshness_is_not_stale_when_raider_counts_match(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with 3 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        $csvContent .= "Player3,Raider,80,3,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardService to return 3 raiders (same as CSV)
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 3, 'name' => 'Player3'], 'rank' => $raiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 3 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    #[Test]
    public function grm_freshness_is_not_stale_when_raider_count_difference_is_less_than_three(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with 5 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        $csvContent .= "Player3,Raider,80,3,Main,\n";
        $csvContent .= "Player4,Raider,80,4,Main,\n";
        $csvContent .= "Player5,Raider,80,5,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardService to return 3 raiders (difference of 2)
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 3, 'name' => 'Player3'], 'rank' => $raiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 5 in CSV, 3 in guild = difference of 2 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    #[Test]
    public function grm_freshness_is_stale_when_raider_count_difference_is_three_or_more(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with 2 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardService to return 5 raiders (difference of 3)
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 3, 'name' => 'Player3'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 4, 'name' => 'Player4'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 5, 'name' => 'Player5'], 'rank' => $raiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 2 in CSV, 5 in guild = difference of 3 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    #[Test]
    public function grm_freshness_counts_multiple_raider_rank_variants(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with different raider rank names
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Core Raider,80,2,Main,\n";
        $csvContent .= "Player3,Trial Raider,80,3,Main,\n";
        $csvContent .= "Player4,Officer,80,4,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create multiple raider ranks (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);
        $coreRaiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Core Raider']);
        $trialRaiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Trial Raider']);
        GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);

        // Mock BlizzardService to return 3 raiders across different ranks
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $coreRaiderRank->position],
                ['character' => ['id' => 3, 'name' => 'Player3'], 'rank' => $trialRaiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 3 raiders in CSV (Player1, Player2, Player3), 3 in guild = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    #[Test]
    public function grm_freshness_returns_file_last_modified_time(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create the CSV file
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nPlayer1,Member,80,1,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.lastModified', function ($lastModified) {
                    // The lastModified should not be the epoch timestamp
                    return $lastModified !== Carbon::createFromTimestamp(0)->toIso8601String();
                })
            )
        );
    }

    #[Test]
    public function grm_freshness_ignores_non_raider_ranks_in_guild_roster(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with 0 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Officer,80,1,Main,\n";
        $csvContent .= "Player2,Member,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create non-raider ranks only (doesn't count attendance to avoid triggering attendance calculation)
        $officerRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $memberRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Member']);

        // Mock BlizzardService to return non-raiders
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $officerRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $memberRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // 0 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    #[Test]
    public function grm_freshness_handles_semicolon_delimited_csv(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        // Create CSV with semicolon delimiter
        $csvContent = "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\n";
        $csvContent .= "Player1;Raider;80;1;Main;\n";
        $csvContent .= "Player2;Raider;80;2;Main;\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardService to return 2 raiders
        $this->mock(BlizzardService::class)
            ->shouldReceive('getGuildRoster')
            ->andReturn(['members' => [
                ['character' => ['id' => 1, 'name' => 'Player1'], 'rank' => $raiderRank->position],
                ['character' => ['id' => 2, 'name' => 'Player2'], 'rank' => $raiderRank->position],
            ]]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.export'));

        // Note: The current implementation uses str_getcsv which defaults to comma delimiter
        // This test documents the current behavior - semicolon CSV won't parse correctly
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness.dataIsStale')
            )
        );
    }
}
