<?php

namespace Tests\Feature\Middleware;

use App\Actions\Navigation\BuildNavigationData;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Contracts\Actions\Navigation\CachesNavigationData;
use App\Enums\SignupStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $viewOfficerDashboard = Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($viewOfficerDashboard);
        DiscordRole::firstOrCreate(['id' => '1467994755953852590'], ['name' => 'Loot Councillor', 'position' => 5, 'is_visible' => true]);
    }

    #[Test]
    public function it_shares_view_officer_dashboard_permission_for_officers(): void
    {
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_does_not_share_view_officer_dashboard_permission_for_raiders(): void
    {
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => ! $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_does_not_share_view_officer_dashboard_permission_for_members(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => ! $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_shares_every_permission_for_admins(): void
    {
        Permission::firstOrCreate(['name' => 'update-warcraft-logs-tags', 'guard_name' => 'web']);
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => $permissions->sort()->values()->all() === Permission::pluck('name')->sort()->values()->all())
            );
    }

    #[Test]
    public function it_shares_empty_permissions_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => $permissions->isEmpty())
            );
    }

    // ==================== user data sharing ====================

    #[Test]
    public function it_shares_user_data_with_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->member()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => 'TestNick',
            'avatar' => 'abc123',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('auth.user.data')
                ->where('auth.user.data.id', '123456789012345678')
                ->where('auth.user.data.username', 'testuser')
                ->where('auth.user.data.nickname', 'TestNick')
                ->where('auth.user.data.display_name', 'TestNick')
                ->where('auth.user.data.highest_role', 'Member')
            );
    }

    #[Test]
    public function it_shares_display_name_as_username_when_nickname_is_null(): void
    {
        $user = User::factory()->member()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => null,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.data.display_name', 'testuser')
            );
    }

    #[Test]
    public function it_shares_null_user_for_guest(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user', null)
            );
    }

    #[Test]
    public function it_shares_avatar_url_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'guild_avatar' => 'avatarhash123',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'auth.user.data.avatar',
                'https://cdn.discordapp.com/guilds/'.config('services.discord.server_id').'/users/123456789012345678/avatars/avatarhash123.webp'
            )
            );
    }

    #[Test]
    public function it_shares_highest_role_for_officer(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->withRoles([$officer->id, $member->id])->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.data.highest_role', 'Officer')
            );
    }

    #[Test]
    public function it_shares_null_highest_role_when_no_recognized_roles(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.data.highest_role', null)
            );
    }

    // ==================== phases scoping ====================

    #[Test]
    public function shared_props_do_not_include_phases(): void
    {
        Phase::factory()->create();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->missing('phases'));
    }

    // ==================== shared props ====================

    #[Test]
    public function it_does_not_share_a_theme_prop(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('theme'));
    }

    #[Test]
    public function it_does_not_share_loose_url_props(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('discordInviteUrl')
                ->missing('warcraftLogsUrl'));
    }

    #[Test]
    public function it_shares_the_sessions_versions_warcraft_logs_url_in_the_navigation(): void
    {
        $older = GameVersion::factory()
            ->for(Guild::factory()->withLogsUrl('https://classic.warcraftlogs.com/guilds/598032-regrowth'), 'warcraftLogsGuild')
            ->create(['release_date' => now()->subYears(2)->toDateString()]);
        GameVersion::factory()
            ->for(Guild::factory()->withLogsUrl('https://fresh.warcraftlogs.com/guilds/774848-regrowth'), 'warcraftLogsGuild')
            ->create(['release_date' => now()->subYear()->toDateString()]);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $older->slug])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navigation.warcraft_logs_url', 'https://classic.warcraftlogs.com/guilds/598032-regrowth'));
    }

    // ==================== game versions ====================

    #[Test]
    public function it_shares_every_game_version_in_switcher_shape_and_order(): void
    {
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addYear()->toDateString()]);
        GameVersion::factory()->create(['release_date' => now()->subYear()->toDateString()]);
        GameVersion::factory()->create(['release_date' => now()->subYears(2)->toDateString()]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('gameVersions.data', 3)
            ->where('gameVersions.data.0.slug', $unreleased->slug)
            ->has('gameVersions.meta.current')
            ->has('gameVersions.data.0', fn (AssertableInertia $version) => $version
                ->hasAll(['id', 'title', 'slug', 'color', 'icon_url', 'release_date'])));
    }

    #[Test]
    public function it_shares_the_key_of_the_current_game_version(): void
    {
        $current = GameVersion::factory()->create(['release_date' => '2022-01-01']);
        GameVersion::factory()->create(['release_date' => '2023-01-01']);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $current->slug])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('gameVersions.meta.current', $current->id)
                ->missing('gameVersions.data.0.meta'));
    }

    #[Test]
    public function it_marks_the_default_version_current_when_the_session_slug_is_stale(): void
    {
        GameVersion::factory()->create(['release_date' => now()->addYear()->toDateString()]);
        $latestReleased = GameVersion::factory()->create(['release_date' => now()->subYear()->toDateString()]);
        GameVersion::factory()->create(['release_date' => now()->subYears(2)->toDateString()]);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'gone'])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('gameVersions.meta.current', $latestReleased->id));
    }

    #[Test]
    public function it_shares_game_versions_without_lazy_loading_media(): void
    {
        Storage::fake('public');
        config(['media-library.force_lazy_loading' => false]);
        $gameVersion = GameVersion::factory()->create();
        GameVersion::factory()->create();
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);

        Model::preventLazyLoading();

        try {
            $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->has('gameVersions.data', 2));
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    #[Test]
    public function it_shares_a_fresh_switcher_icon_after_the_icon_changes(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $this->get('/')->assertOk();

        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('gameVersions.data.0.icon_url', $gameVersion->fresh()->getFirstMediaUrl(GameVersion::MEDIA_ICON)));
    }

    // ==================== navigation ====================

    #[Test]
    public function it_sends_navigation_on_the_initial_page_load(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $event = $this->eventTomorrow($gameVersion);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navigation.next_raid.id', $event->id)
                ->where('navigation.next_raid.signup', null)
                ->has('navigation.latest_discussions', 0));
    }

    #[Test]
    public function navigation_gives_a_member_their_signup_line(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $event = $this->eventTomorrow($gameVersion);
        $user = User::factory()->member()->create();
        $event->characters()->attach(Character::factory()->for($user)->create(), ['signup_status' => SignupStatus::Confirmed]);

        $this->actingAs($user)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navigation.next_raid.signup.label', "You're signed up."));
    }

    #[Test]
    public function navigation_follows_the_session_version(): void
    {
        $first = GameVersion::factory()->create(['release_date' => '2024-01-01']);
        $second = GameVersion::factory()->create(['release_date' => '2023-01-01']);
        $firstEvent = $this->eventTomorrow($first);
        $secondEvent = $this->eventTomorrow($second);

        foreach ([[$first, $firstEvent], [$second, $secondEvent]] as [$gameVersion, $event]) {
            $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
                ->get('/')
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('navigation.next_raid.id', $event->id));
        }
    }

    #[Test]
    public function an_inertia_visit_marks_navigation_as_a_once_prop(): void
    {
        $this->freezeTime();
        $gameVersion = GameVersion::factory()->create();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)
            ->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
            ->inertiaRequest();

        $response->assertJsonPath('props.navigation.next_raid', null);
        $this->assertSame([
            "navigation:{$gameVersion->id}:{$user->id}" => [
                'prop' => 'navigation',
                'expiresAt' => now()->addMinutes(CachesNavigationData::TTL_MINUTES)->getTimestamp() * 1000,
            ],
        ], $response->json('onceProps'));
    }

    #[Test]
    public function an_inertia_visit_keeps_navigation_once_metadata_without_building_it(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $key = "navigation:{$gameVersion->id}:guest";
        BuildNavigationData::shouldNotRun();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
            ->inertiaRequest(['X-Inertia-Except-Once-Props' => $key]);

        $response->assertJsonMissingPath('props.navigation');
        $this->assertSame('navigation', $response->json("onceProps.{$key}.prop"));
    }

    #[Test]
    public function navigation_once_key_changes_with_the_game_version_and_viewer(): void
    {
        $first = GameVersion::factory()->create(['release_date' => '2024-01-01']);
        $second = GameVersion::factory()->create(['release_date' => '2023-01-01']);
        $memberA = User::factory()->member()->create();
        $memberB = User::factory()->member()->create();

        $keys = [];

        foreach ([[$second, null], [$first, null], [$first, $memberA], [$first, $memberB]] as [$gameVersion, $user]) {
            if ($user !== null) {
                $this->actingAs($user);
            }

            $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $gameVersion->slug])
                ->inertiaRequest();

            $keys = [...$keys, ...array_keys($response->json('onceProps'))];
        }

        $this->assertSame([
            "navigation:{$second->id}:guest",
            "navigation:{$first->id}:guest",
            "navigation:{$first->id}:{$memberA->id}",
            "navigation:{$first->id}:{$memberB->id}",
        ], $keys);
    }

    #[Test]
    public function navigation_once_key_follows_a_switch_to_the_records_game_version(): void
    {
        $first = GameVersion::factory()->create(['release_date' => '2024-01-01']);
        $second = GameVersion::factory()->create(['release_date' => '2023-01-01']);
        $raid = Raid::factory()->for(Phase::factory()->state(['game_version_id' => $second->id]))->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $first->slug])
            ->inertiaRequest(
                ['X-Inertia-Except-Once-Props' => "navigation:{$first->id}:guest"],
                route('raiding.raids.show', ['raid' => $raid, 'slug' => $raid->slug]),
            );

        $this->assertSame(["navigation:{$second->id}:guest"], array_keys($response->json('onceProps')));
    }

    #[Test]
    public function navigation_once_key_uses_none_without_a_game_version(): void
    {
        $this->assertSame(['navigation:none:guest'], array_keys($this->inertiaRequest()->json('onceProps')));
    }

    private function eventTomorrow(GameVersion $gameVersion): Event
    {
        return Event::factory()->live()->forGameVersion($gameVersion)->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    /**
     * Visits the URI as an Inertia request at the current asset version, with the given headers.
     *
     * @param  array<string, string>  $headers
     */
    private function inertiaRequest(array $headers = [], string $uri = '/'): TestResponse
    {
        return $this->get($uri, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create($uri)),
            ...$headers,
        ]);
    }
}
