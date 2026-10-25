<?php

namespace Tests\Feature\GuildRosterManager;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRequest;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\DashboardTestCase;

#[Group('characters')]
class ImportControllerTest extends DashboardTestCase
{
    use MocksBlizzardServices;

    private const string CSV = "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // The create tests below that don't fake Saloon send no Blizzard request: `memberCount` and
    // `uploadStatuses` are deferred and only run on a follow-up request. Task 0's guard throws
    // StrayRequestException if that ever stops being true.

    // ==================== create — authorization ====================

    #[Group('authorization')]
    #[Test]
    public function create_requires_authentication(): void
    {
        $response = $this->get(route('management.grm.create'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function create_forbids_member_users(): void
    {
        $response = $this->actingAs(User::factory()->member()->create())->get(route('management.grm.create'));

        $response->assertForbidden();
    }

    // ==================== store — authorization ====================

    #[Group('authorization')]
    #[Test]
    public function store_requires_authentication(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertRedirect('/login');
        Queue::assertNothingPushed();
    }

    #[Group('authorization')]
    #[Test]
    public function store_forbids_guest_users(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs(User::factory()->guest()->create())
            ->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertForbidden();
        Queue::assertNotPushed(ProcessGrmUpload::class);
    }

    #[Group('authorization')]
    #[Test]
    public function store_forbids_member_users(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs(User::factory()->member()->create())
            ->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertForbidden();
        Queue::assertNotPushed(ProcessGrmUpload::class);
    }

    #[Group('authorization')]
    #[Test]
    public function store_forbids_raider_users(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs(User::factory()->raider()->create())
            ->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertForbidden();
        Queue::assertNotPushed(ProcessGrmUpload::class);
    }

    // ==================== create — game version selection ====================

    #[Group('happy-path')]
    #[Test]
    public function create_defaults_to_the_newest_current_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'release_date' => now()->subYear()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => now()->subWeek()]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GRM/Create')
            ->where('gameVersion.data.slug', 'newer')
        );
    }

    #[Test]
    public function it_preselects_the_version_in_its_own_query(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create', ['game_version' => 'older']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'older'));
    }

    #[Test]
    public function it_preselects_the_session_version_when_it_is_a_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'older'])
            ->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'older'));
    }

    #[Test]
    public function viewing_the_page_never_writes_the_session(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'newer'])
            ->get(route('management.grm.create', ['game_version' => 'older']));

        $response->assertOk();
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, 'newer');
    }

    #[Test]
    public function an_upload_leaves_the_session_alone_and_redirects_to_its_version(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'other', 'release_date' => now()->subWeek()]);
        $version = GameVersion::factory()->fetchableRoster()->create(['slug' => 'target', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'other'])
            ->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertRedirect(route('management.grm.create', ['game_version' => $version->slug]));
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, 'other');
        $this->get(route('management.grm.create', ['game_version' => $version->slug]))
            ->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'target'));
        $this->assertSame('other', session(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_does_not_shadow_the_shared_game_versions_prop(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['release_date' => now()->subWeek()]);
        GameVersion::factory()->create(['blizzard_namespace' => null, 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('gameVersions.data', 2)
            ->has('rosterVersions.data', 1)
        );
    }

    #[Test]
    public function create_lists_only_current_rosters_newest_first(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Older', 'guild_name' => 'Older Guild', 'release_date' => now()->subYear()]);
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Newer', 'guild_name' => 'Regrowth', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Deprecated', 'guild_name' => 'Regrowth', 'release_date' => now()->subMonths(6)]);
        GameVersion::factory()->fetchableRoster()->create(['title' => 'Unreleased', 'release_date' => now()->addMonth()]);
        GameVersion::factory()->create(['title' => 'No namespace', 'blizzard_namespace' => null]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('rosterVersions.data', 2)
            ->where('rosterVersions.data.0.title', 'Newer')
            ->where('rosterVersions.data.1.title', 'Older')
        );
    }

    #[Test]
    public function create_falls_back_to_the_newest_current_roster_for_an_unknown_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'tbc']);

        $response = $this->actingAs($this->officer)->followingRedirects()->get(route('management.grm.create', ['game_version' => 'nope']));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'tbc'));
    }

    #[Test]
    public function create_falls_back_to_the_newest_current_roster_for_a_game_version_without_a_current_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'guild_name' => 'Regrowth', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'guild_name' => 'Regrowth', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)->followingRedirects()->get(route('management.grm.create', ['game_version' => 'older']));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'newer'));
    }

    #[Test]
    public function create_renders_without_a_game_version_when_none_has_a_current_roster(): void
    {
        GameVersion::factory()->create(['blizzard_namespace' => null]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion', null)
            ->has('rosterVersions.data', 0)
            ->where('lastUploadTimestamp', null)
        );
    }

    // ==================== create — deferred props ====================

    #[Test]
    public function create_member_count_comes_from_the_selected_guilds_member_count(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Regrowth', 'release_date' => now()->subWeek()]);
        $selected = GameVersion::factory()->fetchableRoster()->create(['guild_name' => 'Sister Guild', 'release_date' => now()->subYear()]);
        $this->mockGetGuild(['member_count' => 623]);
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $selected->slug])
            ->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('memberCount')
            ->loadDeferredProps('default', fn (Assert $reload) => $reload->where('memberCount', 623))
        );
        Saloon::assertSent(fn (Request $request) => $request instanceof GetGuildRequest
            && $request->resolveEndpoint() === '/data/wow/guild/thunderstrike/sister-guild');
    }

    #[Test]
    public function create_upload_statuses_are_keyed_by_game_version_slug(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'uploaded', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'empty', 'release_date' => now()->subYear()]);
        Storage::disk('local')->put('grm/uploads/uploaded/latest.csv', self::CSV);
        $this->mockGetGuild(['member_count' => 1]);
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('uploadStatuses')
            ->loadDeferredProps('statuses', fn (Assert $reload) => $reload
                ->where('uploadStatuses.uploaded', 'current')
                ->where('uploadStatuses.empty', 'missing')
            )
        );
    }

    #[Group('error-handling')]
    #[Test]
    public function create_still_renders_and_resolves_other_versions_when_one_versions_blizzard_call_fails(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'healthy', 'guild_name' => 'Healthy Guild', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'broken', 'guild_name' => 'Broken Guild', 'release_date' => now()->subYear()]);
        Storage::disk('local')->put('grm/uploads/healthy/latest.csv', self::CSV);
        Storage::disk('local')->put('grm/uploads/broken/latest.csv', self::CSV);
        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            // The trailing * is required: the connector appends ?locale=… to every URL.
            '*/data/wow/guild/thunderstrike/healthy-guild*' => MockResponse::make(body: ['id' => 1, 'name' => 'Healthy Guild', 'member_count' => 1], status: 200),
            '*/data/wow/guild/thunderstrike/broken-guild*' => MockResponse::make(body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], status: 404),
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('statuses', fn (Assert $reload) => $reload
                ->where('uploadStatuses.healthy', 'current')
                ->where('uploadStatuses.broken', 'unknown')
            )
        );
        // CountGuildMembers swallows a missing fake, so prove the broken guild was really requested and failed.
        Saloon::assertSent(fn (Request $request) => $request instanceof GetGuildRequest
            && $request->resolveEndpoint() === '/data/wow/guild/thunderstrike/broken-guild');
    }

    // ==================== create — last upload ====================

    #[Test]
    public function create_last_upload_timestamp_uses_the_selected_game_versions_upload(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'alpha', 'release_date' => now()->subWeek()]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'beta', 'release_date' => now()->subYear()]);
        Storage::disk('local')->put('grm/uploads/beta/latest.csv', self::CSV);
        touch(Storage::disk('local')->path('grm/uploads/beta/latest.csv'), 1790000000);

        $response = $this->actingAs($this->officer)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'beta'])
            ->get(route('management.grm.create'));

        $response->assertInertia(fn (Assert $page) => $page->where('lastUploadTimestamp', 'Monday, 21 September 2026 at 16:13'));
    }

    // ==================== store — validation ====================

    #[Group('validation')]
    #[Test]
    public function store_validates_grm_data_required(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->post($this->storeUrl($version), []);

        $response->assertSessionHasErrors(['grm_data' => 'GRM data is required.']);
        Queue::assertNothingPushed();
    }

    #[Group('validation')]
    #[Test]
    public function store_validates_csv_has_header_and_data_rows(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->post($this->storeUrl($version), [
            'grm_data' => 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts',
        ]);

        $response->assertSessionHasErrors(['grm_data']);
        Queue::assertNothingPushed();
    }

    #[Group('validation')]
    #[Test]
    public function store_validates_required_headers_present(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->post($this->storeUrl($version), [
            'grm_data' => "Name,Rank\nTestChar,Raider",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
        Queue::assertNothingPushed();
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_csv_without_valid_delimiter(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create();

        $response = $this->actingAs($this->officer)->post($this->storeUrl($version), [
            'grm_data' => "Name|Rank|Level|Last Online (Days)|Main/Alt|Player Alts\nTestChar|Raider|80|1|Main|",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
        Queue::assertNothingPushed();
    }

    #[Group('validation')]
    #[Test]
    public function store_validates_game_version_required(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);

        $response = $this->actingAs($this->officer)->post(route('management.grm.store'), ['grm_data' => self::CSV]);

        $response->assertSessionHasErrors(['game_version' => 'A game version is required.']);
        Queue::assertNothingPushed();
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_game_version_without_a_current_roster(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'guild_name' => 'Regrowth', 'release_date' => now()->subWeek()]);
        $deprecated = GameVersion::factory()->fetchableRoster()->create(['slug' => 'older', 'guild_name' => 'Regrowth', 'release_date' => now()->subYear()]);

        $response = $this->actingAs($this->officer)->post($this->storeUrl($deprecated), ['grm_data' => self::CSV]);

        $response->assertSessionHasErrors(['game_version' => 'The selected game version is not available for GRM upload.']);
        Storage::disk('local')->assertMissing('grm/uploads/older/latest.csv');
        Queue::assertNothingPushed();
    }

    // ==================== store — saving the upload ====================

    #[Group('happy-path')]
    #[Test]
    public function store_saves_the_upload_queues_it_and_redirects_to_the_game_versions_form(): void
    {
        Queue::fake([ProcessGrmUpload::class, AttachAvatarToUser::class]);
        $version = GameVersion::factory()->fetchableRoster()->create(['slug' => 'tbc']);

        $response = $this->actingAs($this->officer)->post($this->storeUrl($version), ['grm_data' => self::CSV]);

        $response->assertRedirect(route('management.grm.create', ['game_version' => 'tbc']));
        $response->assertSessionHas('success');
        Storage::disk('local')->assertExists('grm/uploads/tbc/latest.csv');
        Storage::disk('local')->assertMissing('grm/uploads/latest.csv');
        Queue::assertPushed(ProcessGrmUpload::class, fn (ProcessGrmUpload $job): bool => $job->userId === $this->officer->id
            && $job->gameVersionId === $version->id
            && $job->grmData['rows'][0]['Name'] === 'TestChar');
    }

    // ==================== helpers ====================

    private function storeUrl(GameVersion $gameVersion): string
    {
        return route('management.grm.store', ['game_version' => $gameVersion->slug]);
    }
}
