<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\User;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-datasets', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'audit-daily-quests', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-boss-strategies', 'guard_name' => 'web']));

        // Mock GuildTags to prevent WarcraftLogs API calls
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();
        $this->app->instance(GuildTags::class, $guildTags);

        Storage::fake('public');

        // Fake Blizzard API requests to prevent live HTTP calls
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [],
            ], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    /**
     * Seed the export file in storage with default data.
     */
    protected function seedExportFile(): void
    {
        $data = [
            'system' => ['date_generated' => Carbon::now()->unix()],
            'priorities' => [],
            'items' => [],
            'players' => [],
            'councillors' => [],
        ];

        Storage::disk('local')->put('addon/export.json', json_encode($data));
    }

    #[Test]
    public function dashboard_index_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function addon_export_page_loads(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function addon_export_json_page_loads(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function addon_export_schema_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function addon_settings_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function manage_ranks_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.ranks.view'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function manage_phases_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function grm_upload_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard/grm-upload');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_form_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.form'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_audit_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.audit'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_audit_page_requires_officer(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.audit'));

        $response->assertForbidden();
    }

    #[Test]
    public function permissions_index_redirects(): void
    {
        Permission::factory()->inGroup('test-group')->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.index'));

        $response->assertRedirect(route('dashboard.permissions.group.show', ['group' => 'test-group']));
    }

    #[Test]
    public function permissions_show_group_page_loads(): void
    {
        Permission::factory()->inGroup('test-group')->create();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.permissions.group.show', ['group' => 'test-group']));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function boss_strategies_index_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.boss-strategies.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function boss_strategies_edit_page_loads(): void
    {
        $user = User::factory()->withPermissions('view-officer-dashboard', 'manage-boss-strategies')->create();
        $boss = Boss::factory()->for(Raid::factory())->create();

        $response = $this->actingAs($user)->get(
            route('dashboard.boss-strategies.edit', ['boss' => $boss, 'slug' => $boss->slug])
        );

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
