<?php

namespace Tests\SmokeTest;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
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

        // Mock GuildTags to prevent WarcraftLogs API calls
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();
        $this->app->instance(GuildTags::class, $guildTags);

        // Mock BlizzardService to prevent Blizzard API calls
        $blizzardService = Mockery::mock(BlizzardService::class);
        $blizzardService->shouldReceive('getGuildRoster')
            ->andReturn(['members' => []])
            ->byDefault();
        $blizzardService->shouldReceive('getPlayableClasses')
            ->andReturn(['classes' => []])
            ->byDefault();
        $blizzardService->shouldReceive('getPlayableRaces')
            ->andReturn(['races' => []])
            ->byDefault();
        $blizzardService->shouldReceive('findPlayableClass')
            ->andReturn([])
            ->byDefault();
        $blizzardService->shouldReceive('getPlayableClassMedia')
            ->andReturn([])
            ->byDefault();
        $blizzardService->shouldReceive('findPlayableRace')
            ->andReturn([])
            ->byDefault();
        $blizzardService->shouldReceive('findItem')
            ->andReturn([])
            ->byDefault();
        $blizzardService->shouldReceive('findMedia')
            ->andReturn([])
            ->byDefault();
        $this->app->instance(BlizzardService::class, $blizzardService);
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
}
