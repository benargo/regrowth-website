<?php

namespace Tests\Feature\Dashboard;

use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\User;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class AddonControllerTest extends DashboardTestCase
{
    use MocksBlizzardServices;

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

        // Fake Saloon to return empty roster by default
        // This prevents real API calls during tests that don't specifically test GRM freshness
        $this->mockGetGuildRoster();
        $this->applyBlizzardMocks();
    }

    // ==================== export & export json ====================

    #[Test]
    public function export_requires_authentication(): void
    {
        $response = $this->get(route('management.addon.export'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function export_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertOk();
    }

    #[Test]
    public function export_json_requires_authentication(): void
    {
        $response = $this->get(route('management.addon.export.json'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function export_json_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.json'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_json_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.json'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function export_json_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('management.addon.export.json'));

        $response->assertForbidden();
    }

    #[Test]
    public function export_json_allows_officer_users(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertOk();
    }

    // ==================== export endpoint ====================

    #[Test]
    public function export_renders_inertia_page_with_base64_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/Export')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/Export')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', '')
            )
        );
    }

    // ==================== export json endpoint ====================

    #[Test]
    public function export_json_renders_inertia_page_with_json_data(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportJson')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportJson')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportJson')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportJson')
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('exportedData')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exportedData', '')
            )
        );
    }

    // ==================== grm freshness ====================

    #[Test]
    public function export_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/Export')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 1)
                ->has('grmFreshness.0.gameVersion')
                ->has('grmFreshness.0.lastModified')
                ->has('grmFreshness.0.dataIsStale')
            )
        );
    }

    #[Test]
    public function export_json_returns_grm_freshness_as_deferred_prop(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export.json'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/ExportJson')
            ->missing('grmFreshness')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 1)
                ->has('grmFreshness.0.gameVersion')
                ->has('grmFreshness.0.lastModified')
                ->has('grmFreshness.0.dataIsStale')
            )
        );
    }

    #[Test]
    public function grm_freshness_lists_one_entry_per_current_roster_newest_first(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Older', 'release_date' => Carbon::now()->subYear()]);
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Newer', 'release_date' => Carbon::now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Unreleased', 'release_date' => Carbon::now()->addMonth()]);
        GameVersion::factory()->create(['title' => 'No namespace', 'blizzard_namespace' => null]);

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 2)
                ->where('grmFreshness.0.gameVersion.title', 'Newer')
                ->where('grmFreshness.1.gameVersion.title', 'Older')
            )
        );
    }

    #[Test]
    public function grm_freshness_is_empty_when_no_game_version_has_a_roster(): void
    {
        Storage::fake('local');
        $this->seedExportFile();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 0)
            )
        );
    }

    #[Group('error-handling')]
    #[Test]
    public function grm_freshness_keeps_other_versions_when_one_roster_fails(): void
    {
        Storage::fake('local');
        $this->seedExportFile();
        $healthy = GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Regrowth', 'release_date' => Carbon::now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Missing Guild', 'release_date' => Carbon::now()->subYear()]);
        $raider = GuildRank::factory()->doesNotCountAttendance()->create(['game_version_id' => $healthy->id, 'sort_order' => 0, 'name' => 'Raider']);

        // Drop the setUp() class-keyed roster mock: Saloon::fake() only adds to the global
        // mock client, and class keys win over URL keys.
        MockClient::destroyGlobal();
        $this->pendingBlizzardMocks = [
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            '*/data/wow/guild/thunderstrike/regrowth/roster*' => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Regrowth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => $this->raiderMemberPayloads(5, $raider->sort_order),
            ], status: 200),
            '*/data/wow/guild/thunderstrike/missing-guild/roster*' => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ];
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)->get(route('management.addon.export'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('grmFreshness', 2)
                ->where('grmFreshness.0.blzRaiderCount', 5)
                ->where('grmFreshness.0.dataIsStale', true)
                ->where('grmFreshness.1.blzRaiderCount', null)
                ->where('grmFreshness.1.dataIsStale', false)
            )
        );
    }

    // ==================== helpers ====================

    /**
     * Seed the export file in storage with the given overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function seedExportFile(array $overrides = []): void
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

    /**
     * Build a single guild roster member payload for the given character id and rank.
     *
     * @return array<string, mixed>
     */
    private function raiderMemberPayload(int $id, int $rank): array
    {
        return [
            'character' => [
                'id' => $id,
                'name' => "Player{$id}",
                'level' => 80,
                'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
            ],
            'rank' => $rank,
        ];
    }

    /**
     * Build guild roster member payloads numbered from 1, all sharing the given rank.
     *
     * @return array<int, array<string, mixed>>
     */
    private function raiderMemberPayloads(int $count, int $rank): array
    {
        return array_map(
            fn (int $id) => $this->raiderMemberPayload($id, $rank),
            range(1, $count),
        );
    }
}
