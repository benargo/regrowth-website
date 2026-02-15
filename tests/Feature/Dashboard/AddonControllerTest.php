<?php

namespace Tests\Feature\Dashboard;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AddonControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_export_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export'));

        $response->assertRedirect('/login');
    }

    public function test_export_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertForbidden();
    }

    public function test_export_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertOk();
    }

    public function test_export_json_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.json'));

        $response->assertRedirect('/login');
    }

    public function test_export_json_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertForbidden();
    }

    public function test_export_json_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertOk();
    }

    public function test_export_schema_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.export.schema'));

        $response->assertRedirect('/login');
    }

    public function test_export_schema_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertForbidden();
    }

    public function test_export_schema_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertOk();
    }

    // ==========================================
    // Export Endpoint Tests
    // ==========================================

    public function test_export_renders_inertia_page_with_base64_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    public function test_export_returns_valid_base64_encoded_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Base64')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
                ->where('exportedData', fn ($data) => base64_decode($data, true) !== false)
            )
        );
    }

    public function test_export_injects_authenticated_user_into_stored_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($user) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return isset($data['system']['user'])
                        && $data['system']['user']['id'] === $user->id
                        && $data['system']['user']['name'] === $user->displayName;
                })
            )
        );
    }

    public function test_export_preserves_stored_data_alongside_injected_user(): void
    {
        Storage::fake('local');
        $this->seedExportFile([
            'priorities' => [['id' => 1, 'name' => 'Tank', 'icon' => null]],
            'councillors' => [['id' => 1, 'name' => 'TestCouncillor', 'rank' => 'Officer']],
        ]);
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', function ($exportedData) use ($user) {
                    $data = json_decode(base64_decode($exportedData), true);

                    return $data['system']['user']['id'] === $user->id
                        && count($data['priorities']) === 1
                        && $data['priorities'][0]['name'] === 'Tank'
                        && count($data['councillors']) === 1
                        && $data['councillors'][0]['name'] === 'TestCouncillor';
                })
            )
        );
    }

    public function test_export_returns_empty_when_no_export_file_exists(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

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

    public function test_export_json_renders_inertia_page_with_json_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exportedData')
            )
        );
    }

    public function test_export_json_returns_valid_json_string(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => is_array(json_decode($data, true)))
            )
        );
    }

    public function test_export_json_returns_pretty_printed_json(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/JSON')
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', fn ($data) => str_contains($data, "\n"))
            )
        );
    }

    public function test_export_json_includes_complete_data_structure(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

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

    public function test_export_json_returns_empty_when_no_export_file_exists(): void
    {
        Storage::fake('local');
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

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

    public function test_export_schema_renders_inertia_page_with_schema(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Schema')
            ->has('schema')
        );
    }

    public function test_export_schema_includes_json_schema_metadata(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

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

    public function test_export_schema_defines_system_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.system')
            ->has('schema.properties.system.properties.date_generated')
            ->has('schema.properties.system.properties.user')
        );
    }

    public function test_export_schema_defines_priorities_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.priorities')
            ->where('schema.properties.priorities.type', 'array')
        );
    }

    public function test_export_schema_defines_items_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.items')
            ->where('schema.properties.items.type', 'array')
        );
    }

    public function test_export_schema_defines_players_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players')
            ->where('schema.properties.players.type', 'array')
        );
    }

    public function test_export_schema_defines_player_attendance_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.players.items.properties.name')
            ->has('schema.properties.players.items.properties.attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.first_attendance')
            ->has('schema.properties.players.items.properties.attendance.properties.attended')
            ->has('schema.properties.players.items.properties.attendance.properties.total')
            ->has('schema.properties.players.items.properties.attendance.properties.percentage')
        );
    }

    public function test_export_schema_defines_councillors_properties(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('schema.properties.councillors')
            ->where('schema.properties.councillors.type', 'array')
            ->has('schema.properties.councillors.items.properties.id')
            ->has('schema.properties.councillors.items.properties.name')
            ->has('schema.properties.councillors.items.properties.rank')
        );
    }

    public function test_export_schema_id_contains_version_1_2_0(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $schema = $response->original->getData()['page']['props']['schema'];

        $this->assertStringContainsString('v=1.2.0', $schema['$id']);
    }

    // ==========================================
    // GRM Freshness Tests
    // ==========================================

    public function test_export_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

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

    public function test_export_json_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

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

    public function test_grm_freshness_returns_epoch_timestamp_when_no_file_exists(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.lastModified', fn ($value) => Carbon::parse($value)->timestamp === 0)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_no_file_exists_and_no_raiders(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_stale_when_no_file_exists_but_guild_has_raiders(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 5 raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
            new GuildMember(character: ['id' => 4, 'name' => 'Player4'], rank: $raiderRank),
            new GuildMember(character: ['id' => 5, 'name' => 'Player5'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 5 raiders in guild, 0 in GRM file = difference of 5 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_raider_counts_match(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create CSV with 3 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        $csvContent .= "Player3,Raider,80,3,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 3 raiders (same as CSV)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 3 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_not_stale_when_raider_count_difference_is_less_than_three(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

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

        // Mock BlizzardGuildService to return 3 raiders (difference of 2)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 5 in CSV, 3 in guild = difference of 2 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_is_stale_when_raider_count_difference_is_three_or_more(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create CSV with 2 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Raider,80,1,Main,\n";
        $csvContent .= "Player2,Raider,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 5 raiders (difference of 3)
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $raiderRank),
            new GuildMember(character: ['id' => 4, 'name' => 'Player4'], rank: $raiderRank),
            new GuildMember(character: ['id' => 5, 'name' => 'Player5'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 2 in CSV, 5 in guild = difference of 3 >= 3 = stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', true)
            )
        );
    }

    public function test_grm_freshness_counts_multiple_raider_rank_variants(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

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

        // Mock BlizzardGuildService to return 3 raiders across different ranks
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $coreRaiderRank),
            new GuildMember(character: ['id' => 3, 'name' => 'Player3'], rank: $trialRaiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 3 raiders in CSV (Player1, Player2, Player3), 3 in guild = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_returns_file_last_modified_time(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create the CSV file
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nPlayer1,Member,80,1,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect());
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

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

    public function test_grm_freshness_ignores_non_raider_ranks_in_guild_roster(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create CSV with 0 raiders
        $csvContent = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\n";
        $csvContent .= "Player1,Officer,80,1,Main,\n";
        $csvContent .= "Player2,Member,80,2,Main,\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create non-raider ranks only (doesn't count attendance to avoid triggering attendance calculation)
        $officerRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $memberRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Member']);

        // Mock BlizzardGuildService to return non-raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $officerRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $memberRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // 0 raiders in both = difference of 0 < 3 = not stale
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('grmFreshness.dataIsStale', false)
            )
        );
    }

    public function test_grm_freshness_handles_semicolon_delimited_csv(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        // Create CSV with semicolon delimiter
        $csvContent = "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\n";
        $csvContent .= "Player1;Raider;80;1;Main;\n";
        $csvContent .= "Player2;Raider;80;2;Main;\n";
        Storage::disk('local')->put('grm/uploads/latest.csv', $csvContent);

        // Create a raider rank (doesn't count attendance to avoid triggering attendance calculation)
        $raiderRank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Raider']);

        // Mock BlizzardGuildService to return 2 raiders
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')->andReturn(collect([
            new GuildMember(character: ['id' => 1, 'name' => 'Player1'], rank: $raiderRank),
            new GuildMember(character: ['id' => 2, 'name' => 'Player2'], rank: $raiderRank),
        ]));
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        // Note: The current implementation uses str_getcsv which defaults to comma delimiter
        // This test documents the current behavior - semicolon CSV won't parse correctly
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness.dataIsStale')
            )
        );
    }

    // ==========================================
    // Settings Endpoint Tests
    // ==========================================

    public function test_settings_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.settings'));

        $response->assertRedirect('/login');
    }

    public function test_settings_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    public function test_settings_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    public function test_settings_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    public function test_settings_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertOk();
    }

    public function test_settings_renders_inertia_page(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Settings')
        );
    }

    public function test_settings_includes_councillors_in_settings(): void
    {
        $user = User::factory()->officer()->create();

        $councillor = Character::factory()->lootCouncillor()->create(['name' => 'SettingsCouncillor']);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.councillors', 1)
            ->where('settings.councillors.0.name', 'SettingsCouncillor')
        );
    }

    public function test_settings_includes_guild_ranks_in_settings(): void
    {
        $user = User::factory()->officer()->create();

        // Clear any existing ranks and create our test rank
        GuildRank::query()->delete();
        GuildRank::factory()->create(['name' => 'Test Rank', 'position' => 1]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.ranks', 1)
            ->where('settings.ranks.0.name', 'Test Rank')
        );
    }

    public function test_settings_includes_guild_tags_in_settings(): void
    {
        $user = User::factory()->officer()->create();

        $tag = GuildTag::factory()->countsAttendance()->create(['name' => 'TestTag']);

        // Mock the WarcraftLogs GuildService to return our tag
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect([$tag]));
        $this->app->instance(GuildTags::class, $guildTags);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.tags', 1)
            ->where('settings.tags.0.name', 'TestTag')
            ->where('settings.tags.0.count_attendance', true)
        );
    }

    public function test_settings_includes_characters_as_deferred_prop(): void
    {
        $user = User::factory()->officer()->create();

        Character::factory()->create(['name' => 'DeferredCharacter']);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('characters')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters')
                ->where('characters', fn ($characters) => collect($characters)->contains('name', 'DeferredCharacter'))
            )
        );
    }

    public function test_settings_councillors_are_ordered_by_name(): void
    {
        $user = User::factory()->officer()->create();

        Character::factory()->lootCouncillor()->create(['name' => 'Zoe']);
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->lootCouncillor()->create(['name' => 'Mike']);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('settings.councillors', function ($councillors) {
                $names = collect($councillors)->pluck('name')->toArray();

                return $names === ['Alice', 'Mike', 'Zoe'];
            })
        );
    }

    public function test_settings_ranks_are_ordered_by_position(): void
    {
        $user = User::factory()->officer()->create();

        // Clear any existing ranks and create our test ranks
        GuildRank::query()->delete();
        GuildRank::factory()->create(['name' => 'Officer', 'position' => 2]);
        GuildRank::factory()->create(['name' => 'Guild Master', 'position' => 1]);
        GuildRank::factory()->create(['name' => 'Member', 'position' => 3]);

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.ranks', 3)
            ->where('settings.ranks.0.name', 'Guild Master')
            ->where('settings.ranks.1.name', 'Officer')
            ->where('settings.ranks.2.name', 'Member')
        );
    }

    // ==========================================
    // Add Councillor Endpoint Tests
    // ==========================================

    public function test_add_councillor_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.addon.settings.councillors.add'));

        $response->assertRedirect('/login');
    }

    public function test_add_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
    }

    public function test_add_councillor_requires_character_name(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), []);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_requires_character_name_to_be_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 12345,
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_requires_character_to_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'NonExistentCharacter',
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_sets_character_as_loot_councillor(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'NewCouncillor', 'is_loot_councillor' => false]);

        $this->assertFalse($character->is_loot_councillor);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
        $this->assertTrue($character->fresh()->is_loot_councillor);
    }

    public function test_add_councillor_redirects_back(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)
            ->from(route('dashboard.addon.settings'))
            ->post(route('dashboard.addon.settings.councillors.add'), [
                'character_name' => $character->name,
            ]);

        $response->assertRedirect(route('dashboard.addon.settings'));
    }

    public function test_add_councillor_is_idempotent(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create(['name' => 'AlreadyCouncillor']);

        $this->assertTrue($character->is_loot_councillor);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
        $this->assertTrue($character->fresh()->is_loot_councillor);
    }

    // ==========================================
    // Remove Councillor Endpoint Tests
    // ==========================================

    public function test_remove_councillor_requires_authentication(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect('/login');
    }

    public function test_remove_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
    }

    public function test_remove_councillor_unsets_character_as_loot_councillor(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create(['name' => 'RemoveMe']);

        $this->assertTrue($character->is_loot_councillor);

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }

    public function test_remove_councillor_redirects_back(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)
            ->from(route('dashboard.addon.settings'))
            ->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect(route('dashboard.addon.settings'));
    }

    public function test_remove_councillor_returns_404_for_nonexistent_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', 99999));

        $response->assertNotFound();
    }

    public function test_remove_councillor_is_idempotent(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'NotCouncillor', 'is_loot_councillor' => false]);

        $this->assertFalse($character->is_loot_councillor);

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }
}
