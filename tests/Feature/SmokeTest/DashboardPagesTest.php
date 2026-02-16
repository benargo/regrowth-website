<?php

namespace Tests\Feature\SmokeTest;

use App\Models\User;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock GuildTags to prevent WarcraftLogs API calls
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();
        $this->app->instance(GuildTags::class, $guildTags);

        // Mock BlizzardGuildService to prevent Blizzard API calls
        $blizzardGuildService = Mockery::mock(BlizzardGuildService::class);
        $blizzardGuildService->shouldReceive('members')
            ->andReturn(collect())
            ->byDefault();
        $this->app->instance(BlizzardGuildService::class, $blizzardGuildService);
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

    public function test_dashboard_index_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_addon_export_page_loads(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_addon_export_json_page_loads(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.json'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_addon_export_schema_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.export.schema'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_addon_settings_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_manage_ranks_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard/manage-ranks');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_manage_phases_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard/manage-phases');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_grm_upload_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/dashboard/grm-upload');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_daily_quests_form_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.form'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
