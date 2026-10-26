<?php

namespace Tests\Feature\Addons;

use App\Actions\GameVersion\RememberGameVersion;
use App\Models\GameVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class LootToolControllerTest extends DashboardTestCase
{
    use MocksBlizzardServices;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('addon');

        // Fake Saloon to return empty roster by default
        // This prevents real API calls during tests that don't specifically test GRM freshness
        $this->mockGetGuildRoster();
        $this->applyBlizzardMocks();
    }

    // ==================== access ====================

    #[Test]
    public function export_requires_authentication(): void
    {
        $this->get(route('addons.loot-tool.export'))->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function export_forbids_non_officer_users(): void
    {
        foreach (['guest', 'member', 'raider'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->actingAs($user)->get(route('addons.loot-tool.export'))->assertForbidden();
        }
    }

    #[Test]
    public function export_allows_officer_users(): void
    {
        $this->seedExportFile();

        $this->actingAs($this->officer)->get(route('addons.loot-tool.export'))->assertOk();
    }

    // ==================== export (json) ====================

    #[Test]
    public function export_defaults_to_json(): void
    {
        $gameVersion = $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Addons/LootTool/Export')
            ->where('gameVersion.data.id', $gameVersion->id)
            ->where('hasExport', true)
            ->missing('json')
            ->missing('base64')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('json', fn ($data) => str_contains($data, "\n") && is_array(json_decode($data, true)))
                ->missing('base64')
            )
        );
    }

    #[Test]
    public function export_injects_authenticated_user_and_preserves_stored_data(): void
    {
        $this->seedExportFile(overrides: [
            'priorities' => [['id' => 1, 'name' => 'Tank', 'icon' => null]],
            'councillors' => [['id' => 1, 'name' => 'TestCouncillor', 'rank' => 'Officer']],
        ]);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('json', function ($json) {
                    $data = json_decode($json, true);

                    return $data['system']['user']['id'] === $this->officer->id
                        && $data['system']['user']['name'] === $this->officer->displayName
                        && $data['priorities'][0]['name'] === 'Tank'
                        && $data['councillors'][0]['name'] === 'TestCouncillor';
                })
            )
        );
    }

    #[Test]
    public function export_returns_empty_when_no_export_file_exists(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        RememberGameVersion::run($gameVersion->slug);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasExport', true)
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('json', ''))
        );
    }

    #[Test]
    public function export_does_not_show_another_game_versions_export(): void
    {
        $this->seedExportFile(overrides: ['priorities' => [['id' => 9, 'name' => 'Other', 'icon' => null]]]);
        $current = GameVersion::factory()->fetchableRoster()->create(['title' => 'Current', 'guild_name' => 'Other Guild']);
        RememberGameVersion::run($current->slug);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.id', $current->id)
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('json', ''))
        );
    }

    #[Test]
    public function export_has_no_export_for_a_version_without_a_current_roster(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => null]);
        RememberGameVersion::run($gameVersion->slug);
        Storage::disk('addon')->put("{$gameVersion->id}/export.json", json_encode(['system' => []]));

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.id', $gameVersion->id)
            ->where('hasExport', false)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('json', '')
                ->has('grmFreshness', 0)
            )
        );
    }

    #[Test]
    public function a_game_version_query_switches_the_current_version_and_redirects(): void
    {
        $a = $this->seedExportFile();
        $b = GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Second Guild']);

        $this->actingAs($this->officer)
            ->get(route('addons.loot-tool.export', ['game_version' => $b->slug]))
            ->assertRedirect(route('addons.loot-tool.export'))
            ->assertStatus(303);

        $this->assertSame($b->slug, session('current_game_version'));
        $this->assertNotSame($a->slug, session('current_game_version'));
    }

    // ==================== export base64 ====================

    #[Test]
    public function export_loads_base64_on_a_partial_reload(): void
    {
        $this->seedExportFile(overrides: ['priorities' => [['id' => 1, 'name' => 'Tank', 'icon' => null]]]);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('base64')
            ->reloadOnly('base64', fn (Assert $reload) => $reload
                ->where('base64', function ($base64) {
                    $data = json_decode(base64_decode($base64, true), true);

                    return $data['priorities'][0]['name'] === 'Tank'
                        && $data['system']['user']['id'] === $this->officer->id;
                })
            )
        );
    }

    #[Test]
    public function export_base64_is_empty_when_no_export_file_exists(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        RememberGameVersion::run($gameVersion->slug);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('base64', fn (Assert $reload) => $reload->where('base64', ''))
        );
    }

    // ==================== grm freshness ====================

    #[Test]
    public function export_checks_freshness_for_the_current_version_only(): void
    {
        $current = $this->seedExportFile();
        GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Second Guild']);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 1)
                ->where('grmFreshness.0.gameVersion.id', $current->id)
                ->has('grmFreshness.0.lastModified')
                ->has('grmFreshness.0.dataIsStale')
                ->has('grmFreshness.0.dataIsOutdated')
            )
        );
    }

    #[Group('error-handling')]
    #[Test]
    public function grm_freshness_survives_a_failing_roster(): void
    {
        $this->seedExportFile(attributes: ['guild_name' => 'Missing Guild']);

        MockClient::destroyGlobal();
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            '*/data/wow/guild/thunderstrike/missing-guild/roster*' => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ];
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 1)
                ->where('grmFreshness.0.blzRaiderCount', null)
                ->where('grmFreshness.0.dataIsStale', false)
            )
        );
    }

    // ==================== helpers ====================

    /**
     * Create a game version with a current roster, make it the current one, and store its export.
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $attributes
     */
    private function seedExportFile(array $overrides = [], array $attributes = []): GameVersion
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create($attributes);
        RememberGameVersion::run($gameVersion->slug);

        $data = array_merge([
            'system' => ['date_generated' => Carbon::now()->unix()],
            'priorities' => [],
            'items' => [],
            'players' => [],
            'councillors' => [],
        ], $overrides);

        Storage::disk('addon')->put("{$gameVersion->id}/export.json", json_encode($data));

        return $gameVersion;
    }
}
