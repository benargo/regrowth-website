<?php

namespace Tests\Feature\Dashboard;

use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Actions\GameVersion\UpdateGameVersion;
use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Enums\Faction;
use App\Enums\GameVersionSetupStep;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Middleware\SetResourceScope;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use Carbon\Carbon;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\Matcher\MatcherInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
#[Group('raiding')]
class GameVersionControllerTest extends DashboardTestCase
{
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.game-versions.index'));

        $response->assertRedirect(route('login'));
    }

    // ==================== index ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_members_from_the_index(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member)->get(route('management.game-versions.index'));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function index_does_not_shadow_the_shared_game_versions_prop(): void
    {
        GameVersion::factory()->create(['release_date' => now()->subYear()]);
        GameVersion::factory()->create(['release_date' => now()->subWeek()]);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('gameVersions.data', 2)
            ->has('gameVersions.meta.current')
            ->has('versions.data', 2)
            ->has('versions.data.0.phases_count')
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_game_versions_by_release_date_with_usage_counts(): void
    {
        $later = GameVersion::factory()->create(['title' => 'Later', 'release_date' => Carbon::create(2026, 6, 1)]);
        GameVersion::factory()->create(['title' => 'Earlier', 'release_date' => Carbon::create(2025, 1, 1)]);
        $phase = Phase::factory()->for($later)->create();
        Raid::factory()->for($phase)->create();

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Index')
            ->has('versions.data', 2)
            ->where('versions.data.0.title', 'Earlier')
            ->where('versions.data.0.phases_count', 0)
            ->where('versions.data.1.title', 'Later')
            ->where('versions.data.1.phases_count', 1)
            ->has('versions.data.1.characters_count')
            ->where('versions.data.1.links', [
                'edit' => route('management.game-versions.edit', $later),
                'destroy' => route('management.game-versions.destroy', $later),
            ])
            ->where('routes', ['create' => route('management.game-versions.create')])
        );
    }

    // ==================== create ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_create_page_with_enum_options(): void
    {
        Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Create')
            ->where('options.factions.0', ['value' => 'Alliance', 'label' => 'Alliance'])
            ->missing('options.themes')
            ->where('options.blizzard_namespaces.0', ['value' => 'anniversary', 'label' => 'Anniversary'])
            ->where('options.warcraft_logs_guilds', [
                ['value' => 774848, 'label' => '774848 (The Burning Crusade Classic Anniversary)'],
            ])
            ->missing('options.warcraftlogs_namespaces')
            ->where('steps', GameVersionSetupStep::options())
            ->where('routes', [
                'index' => route('management.game-versions.index'),
                'store' => route('management.game-versions.store'),
            ])
        );
    }

    // ==================== store ====================

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_game_version_and_redirects_to_the_first_setup_step(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload());

        $gameVersion = GameVersion::sole();
        $response->assertRedirect(route('management.game-versions.setup', [$gameVersion, 'theme']));
        $response->assertSessionHas('success', 'Added Wrath Classic.');

        $this->assertSame('Wrath Classic', $gameVersion->title);
        $this->assertSame('wrath', $gameVersion->slug);
        $this->assertSame('Gehennas', $gameVersion->realm);
        $this->assertSame('the Old Guard', $gameVersion->guild_name);
        $this->assertSame(Faction::HORDE, $gameVersion->faction);
        $this->assertSame('8b7ed0', $gameVersion->color);
        $this->assertSame(BlizzardNamespace::CLASSIC, $gameVersion->blizzard_namespace);
        $this->assertNull($gameVersion->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_stores_a_date_only_release_date_at_midnight_in_the_app_timezone(): void
    {
        $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'release_date' => '2026-02-06',
        ]));

        $this->assertTrue(
            GameVersion::sole()->release_date->equalTo(Carbon::create(2026, 2, 6, 0, 0, 0, 'Europe/Paris'))
        );
    }

    #[Test]
    public function it_stores_no_color_when_none_is_chosen(): void
    {
        $response = $this->actingAs($this->officer)->post(
            route('management.game-versions.store'),
            Arr::except($this->validPayload(), 'color'),
        );

        $response->assertValid();
        $this->assertNull(GameVersion::sole()->color);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_store_without_edit_datasets_permission(): void
    {
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->post(route('management.game-versions.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_title_and_release_date(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), []);

        $response->assertInvalid([
            'title' => 'The game version title is required.',
            'release_date' => 'The release date is required.',
        ]);
        $response->assertValid('color');
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_values_outside_the_enums(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'faction' => 'Scourge',
            'blizzard_namespace' => 'forever',
        ]));

        $response->assertInvalid([
            'faction' => 'The selected faction is invalid.',
            'blizzard_namespace' => 'The selected Blizzard API namespace is invalid.',
        ]);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[TestWith(['neon'])]
    #[TestWith(['#fff'])]
    #[TestWith(['#8b7ed0ff'])]
    public function it_rejects_a_color_that_is_not_a_six_digit_hex_code(string $color): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'color' => $color,
        ]));

        $response->assertInvalid(['color' => 'The colour must be a six-digit hex code, for example #8B7ED0.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_warcraft_logs_guild_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'warcraft_logs_guild_id' => 999999,
        ]));

        $response->assertInvalid([
            'warcraft_logs_guild_id' => 'Choose one of the listed Warcraft Logs guilds.',
        ]);
        $this->assertDatabaseCount('game_versions', 0);
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_game_version_linked_to_an_existing_guild(): void
    {
        $guild = Guild::factory()->create();

        $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'warcraft_logs_guild_id' => $guild->id,
        ]));

        $this->assertSame($guild->id, GameVersion::sole()->warcraft_logs_guild_id);
        $this->assertDatabaseCount('warcraft_logs_guilds', 1);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_duplicate_title_on_store(): void
    {
        GameVersion::factory()->create(['title' => 'Wrath Classic']);

        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload());

        $response->assertInvalid(['title' => 'A game version with this title already exists.']);
        $this->assertDatabaseCount('game_versions', 1);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_slug(): void
    {
        $response = $this->actingAs($this->officer)->post(
            route('management.game-versions.store'),
            Arr::except($this->validPayload(), 'slug')
        );

        $response->assertInvalid(['slug' => 'The slug is required.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Test]
    public function it_converts_the_slug_to_a_slug_before_storing_it(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => 'tHe CruSade',
        ]));

        $response->assertValid();
        $this->assertSame('the-crusade', GameVersion::sole()->slug);
    }

    #[Test]
    public function it_trims_dashes_from_the_start_and_end_of_the_slug(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => '--tbc anniversary-',
        ]));

        $response->assertValid();
        $this->assertSame('tbc-anniversary', GameVersion::sole()->slug);
    }

    #[Test]
    public function it_accepts_a_slug_longer_than_the_recommended_sixteen_characters(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => 'the burning crusade anniversary',
        ]));

        $response->assertValid();
        $this->assertSame('the-burning-crusade-anniversary', GameVersion::sole()->slug);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_slug_that_is_only_too_long_once_converted(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => str_repeat('a', 252).'@b',
        ]));

        $response->assertInvalid(['slug' => 'The slug field must not be greater than 255 characters.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_slug_that_duplicates_an_existing_one_once_converted(): void
    {
        GameVersion::factory()->create(['slug' => 'tbc']);

        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => 'TBC',
        ]));

        $response->assertInvalid(['slug' => 'A game version with this slug already exists.']);
        $this->assertDatabaseCount('game_versions', 1);
    }

    #[Group('validation')]
    #[Test]
    #[TestWith(['manage'])]
    #[TestWith(['Loot'])]
    #[TestWith(['roster'])]
    #[TestWith(['up'])]
    public function it_rejects_a_slug_that_is_already_the_start_of_a_site_url(string $slug): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'slug' => $slug,
        ]));

        $response->assertInvalid(['slug' => 'This slug is already used by another part of the site.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_guild_name(): void
    {
        $response = $this->actingAs($this->officer)->post(
            route('management.game-versions.store'),
            Arr::except($this->validPayload(), 'guild_name')
        );

        $response->assertInvalid(['guild_name' => 'The guild name is required.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_guild_name_longer_than_twenty_four_characters(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'guild_name' => str_repeat('a', 25),
        ]));

        $response->assertInvalid(['guild_name' => 'The guild name field must not be greater than 24 characters.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Test]
    public function it_stores_a_guild_name_of_twenty_four_accented_characters(): void
    {
        $guildName = str_repeat('é', 24);

        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'guild_name' => $guildName,
        ]));

        $response->assertValid();
        $this->assertSame($guildName, GameVersion::sole()->guild_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_whether_characters_have_surnames(): void
    {
        $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'uses_surnames' => true,
        ]));

        $this->assertTrue(GameVersion::sole()->uses_surnames);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_whether_characters_have_surnames(): void
    {
        $response = $this->actingAs($this->officer)->post(
            route('management.game-versions.store'),
            Arr::except($this->validPayload(), 'uses_surnames')
        );

        $response->assertInvalid(['uses_surnames' => 'The surnames field is required.']);
        $this->assertDatabaseCount('game_versions', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_surnames_flag_that_is_not_true_or_false(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.game-versions.store'), $this->validPayload([
            'uses_surnames' => 'sometimes',
        ]));

        $response->assertInvalid(['uses_surnames' => 'The surnames field must be true or false.']);
    }

    // ==================== edit ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_edit_page_with_the_game_version_and_options(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'title' => 'Era',
            'release_date' => Carbon::create(2019, 8, 27, 0, 0, 0, 'Europe/Paris'),
            'faction' => Faction::HORDE,
        ]);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Edit')
            ->where('gameVersion.data.id', $gameVersion->id)
            ->where('gameVersion.data.title', 'Era')
            ->where('gameVersion.data.release_date', '2019-08-27')
            ->where('gameVersion.data.faction', 'Horde')
            ->where('gameVersion.media', [
                GameVersion::MEDIA_BANNER => ['accepts' => ['image/jpeg', 'image/png', 'image/webp']],
                GameVersion::MEDIA_ICON => ['accepts' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']],
                GameVersion::MEDIA_LOGO => ['accepts' => ['image/jpeg', 'image/png', 'image/webp']],
            ])
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_shares_the_relationship_checklists_on_the_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('relationships', $relationships)
        );
    }

    #[Test]
    public function it_shares_the_linked_setup_steps_and_page_urls_on_the_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('steps', count(GameVersionSetupStep::cases()))
            ->where('steps.0.href', route('management.game-versions.setup', [$gameVersion, 'theme']))
            ->where('routes', [
                'index' => route('management.game-versions.index'),
                'update' => route('management.game-versions.update', $gameVersion),
                'review' => route('management.game-versions.review', $gameVersion),
            ])
        );
    }

    #[Test]
    #[Group('contract')]
    #[TestWith(['management.game-versions.index'])]
    #[TestWith(['management.game-versions.edit'])]
    #[TestWith(['management.game-versions.setup'])]
    #[TestWith(['management.game-versions.review'])]
    public function the_management_pages_scope_the_game_version_resource_for_the_request(string $routeName): void
    {
        $this->assertContains(
            SetResourceScope::class.':'.GameVersionResource::class.',management',
            Route::getRoutes()->getByName($routeName)->gatherMiddleware(),
        );
    }

    #[Test]
    public function the_edit_page_receives_the_management_shape(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        $gameVersion = GameVersion::factory()->create(['realm' => 'Thunderstrike', 'warcraft_logs_guild_id' => $guild->id]);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.realm', 'Thunderstrike')
            ->where('gameVersion.data.warcraftlogs.namespace.value', WarcraftLogsNamespace::Anniversary->value)
        );
    }

    #[Test]
    public function it_shares_whether_characters_have_surnames_on_the_edit_page(): void
    {
        $gameVersion = GameVersion::factory()->withSurnames()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.edit', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.uses_surnames', true));
    }

    // ==================== edit lock ====================

    #[Group('happy-path')]
    #[Test]
    public function it_gives_the_first_officer_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', true)
            ->where('editor', null)
        );
    }

    #[Test]
    public function it_shows_the_edit_page_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create(['nickname' => 'Holder']);
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_shows_a_setup_step_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create();
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)
            ->get(route('management.game-versions.setup', [$gameVersion, GameVersionSetupStep::first()]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_frees_the_edit_lock_once_the_holder_stops_refreshing_it(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        $this->fakeRelationships($gameVersion);

        $this->travel(GameVersion::EDIT_LOCK_SECONDS + 1)->seconds();
        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_keeps_the_edit_lock_through_a_throttled_background_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        $this->fakeRelationships($gameVersion);

        $this->travel(61)->seconds();
        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    }

    #[Test]
    public function it_does_not_renew_the_edit_lock_on_an_idle_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        BuildGameVersionRelationships::shouldRun()->andReturn([]);

        $this->travel(GameVersion::EDIT_LOCK_SECONDS - 10)->seconds();
        $idlePoll = $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));
        $this->travel(11)->seconds();
        $otherOfficer = $this->actingAs(User::factory()->officer()->create())
            ->withHeaders(['X-Edit-Idle' => null])
            ->get($this->editUrl($gameVersion));

        $idlePoll->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
        $otherOfficer->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_does_not_take_a_free_edit_lock_on_an_idle_poll(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));

        $this->assertFalse($gameVersion->isLockedForEditingBy(User::factory()->officer()->create()));
    }

    #[Test]
    public function it_shows_an_idle_officer_who_is_editing_once_someone_else_takes_the_lock(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $holder = User::factory()->officer()->create();
        $gameVersion->acquireEditLock($holder);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)
            ->withHeader('X-Edit-Idle', '1')
            ->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_lets_the_holder_keep_the_edit_lock_across_visits(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    #[Test]
    public function it_rejects_an_update_while_another_officer_holds_the_edit_lock(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => 'Gehennas']);
        $gameVersion->acquireEditLock(User::factory()->officer()->create());
        UpdateGameVersion::shouldRun()->never();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHasErrors(['edit_lock' => 'Someone else is editing this game version. Your change was not saved.']);
    }

    #[Test]
    public function it_accepts_an_update_from_the_edit_lock_holder(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->acquireEditLock($this->officer);
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_refuses_to_delete_a_game_version_another_officer_is_editing(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Busy']);
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        $response = $this->actingAs($this->officer)
            ->from(route('management.game-versions.index'))
            ->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('error', "Someone is editing Busy, so it can't be deleted right now.");
        $this->assertModelExists($gameVersion);
    }

    // ==================== setup ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_setup_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        BuildGameVersionRelationships::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.game-versions.setup', [$gameVersion, 'phases']));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_renders_a_setup_step_with_its_neighbours_and_relationship_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'phases']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Setup')
            ->where('gameVersion.data.title', 'Era')
            ->where('step', GameVersionSetupStep::PHASES->toOption())
            ->where('previousStep', GameVersionSetupStep::RACES_AND_CLASSES->toOption())
            ->where('nextStep', GameVersionSetupStep::GUILD_RANKS->toOption())
            ->has('steps', count(GameVersionSetupStep::cases()))
            ->where('steps.2.href', route('management.game-versions.setup', [$gameVersion, 'phases']))
            ->where('relationships', $relationships)
            ->where('returnToReview', false)
            ->where('routes', [
                'update' => route('management.game-versions.update', $gameVersion),
                'edit' => route('management.game-versions.edit', $gameVersion),
                'review' => route('management.game-versions.review', $gameVersion),
                'previous' => route('management.game-versions.setup', [$gameVersion, 'races-and-classes']),
                'next' => route('management.game-versions.setup', [$gameVersion, 'guild-ranks']),
            ])
        );
    }

    #[Test]
    public function it_shares_return_to_review_on_a_setup_step_opened_from_the_review_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'phases', 'review' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('returnToReview', true)
            ->where('routes.next', route('management.game-versions.review', $gameVersion))
        );
    }

    #[Test]
    public function it_shares_no_next_step_on_the_last_setup_step(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, 'guild-ranks']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('nextStep', null)
        );
    }

    #[Test]
    #[TestWith(['zones'])]
    #[TestWith(['guild-tags'])]
    public function it_returns_not_found_for_an_unknown_setup_step(string $step): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.setup', [$gameVersion, $step]));

        $response->assertNotFound();
    }

    // ==================== review ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_review_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        BuildGameVersionRelationships::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)->get(route('management.game-versions.review', $gameVersion));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_review_page_with_the_details_steps_and_relationships(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $relationships = $this->fakeRelationships($gameVersion);

        $response = $this->actingAs($this->officer)->get(route('management.game-versions.review', $gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GameVersions/Review')
            ->where('gameVersion.data.title', 'Era')
            ->has('steps', count(GameVersionSetupStep::cases()))
            ->where('steps.3.href', route('management.game-versions.setup', [$gameVersion, 'guild-ranks']))
            ->where('relationships', $relationships)
            ->where('routes', [
                'index' => route('management.game-versions.index'),
                'edit' => route('management.game-versions.edit', $gameVersion),
                'review' => route('management.game-versions.review', $gameVersion),
            ])
        );
    }

    // ==================== update ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_a_game_version_and_redirects_back(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', 'Saved changes to Era.');
    }

    #[Test]
    public function it_saves_an_autosave_without_flashing_a_message(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionMissing('success');
    }

    #[Test]
    public function it_allows_an_update_that_keeps_the_same_title(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Wrath Classic']);
        $this->expectUpdate($gameVersion, $this->validUpdatePayload());

        $response = $this->actingAs($this->officer)->patch(
            route('management.game-versions.update', $gameVersion),
            $this->validUpdatePayload()
        );

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_update_to_another_game_versions_title(): void
    {
        GameVersion::factory()->create(['title' => 'Taken']);
        $gameVersion = GameVersion::factory()->create(['title' => 'Mine']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)->patch(
            route('management.game-versions.update', $gameVersion),
            $this->validUpdatePayload(['title' => 'Taken'])
        );

        $response->assertInvalid(['title' => 'A game version with this title already exists.']);
    }

    #[Test]
    public function it_passes_optional_fields_submitted_blank_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, $this->validUpdatePayload([
            'title' => $gameVersion->title,
            'realm' => null,
            'faction' => null,
            'blizzard_namespace' => null,
            'warcraft_logs_guild_id' => null,
        ]));

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), $this->validUpdatePayload([
            'title' => $gameVersion->title,
            'realm' => '',
            'faction' => '',
            'blizzard_namespace' => '',
            'warcraft_logs_guild_id' => '',
        ]));

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_ignores_the_retired_warcraftlogs_expansion_field(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Gehennas']);

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), [
            'realm' => 'Gehennas',
            'warcraftlogs_expansion' => 1001,
        ]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_ignores_the_retired_warcraftlogs_guild_fields(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Gehennas']);

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), [
            'realm' => 'Gehennas',
            'warcraftlogs_guild' => 774848,
            'warcraftlogs_namespace' => 'anniversary',
        ]);

        $response->assertValid();
    }

    #[Test]
    public function it_links_an_existing_guild_on_update(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $guild = Guild::factory()->create();
        $this->expectUpdate($gameVersion, ['warcraft_logs_guild_id' => $guild->id]);

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), [
            'warcraft_logs_guild_id' => $guild->id,
        ]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_guild_on_update(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->patch(route('management.game-versions.update', $gameVersion), [
            'warcraft_logs_guild_id' => 999999,
        ]);

        $response->assertInvalid(['warcraft_logs_guild_id' => 'Choose one of the listed Warcraft Logs guilds.']);
        $this->assertNull($gameVersion->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function the_edit_page_shows_the_linked_guild_and_its_namespace(): void
    {
        $guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();

        $response = $this->actingAs($this->officer)->get($this->editUrl($gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.warcraftlogs.guild', 774848)
            ->where('gameVersion.data.warcraftlogs.namespace.value', 'anniversary')
            ->where('gameVersion.data.warcraftlogs.namespace.label', 'The Burning Crusade Classic Anniversary')
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_update_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($dashboardOnlyUser)
            ->patch(route('management.game-versions.update', $gameVersion), ['title' => 'Theirs']);

        $response->assertForbidden();
    }

    #[Test]
    public function it_accepts_an_update_that_sends_only_some_fields(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['realm' => 'Firemaw']);

        $response->assertValid();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_guild_ranks_in_the_order_given(): void
    {
        Queue::fake();
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.game-versions.edit', $gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'guild_ranks' => [
                    ['id' => null, 'name' => 'Guild Master', 'count_attendance' => false],
                    ['id' => null, 'name' => 'Raider', 'count_attendance' => true],
                ],
            ]);

        $response->assertRedirect(route('management.game-versions.edit', $gameVersion));
        $this->assertSame(['Guild Master', 'Raider'], $gameVersion->guildRanks()->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_rejects_a_guild_rank_from_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $ownRank = GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        $otherRank = GuildRank::factory()->for(GameVersion::factory())->create(['name' => 'Officer']);

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                'guild_ranks' => [['id' => $otherRank->id, 'name' => 'Stolen', 'count_attendance' => true]],
            ]);

        $response->assertSessionHasErrors(['guild_ranks.0.id']);
        $this->assertModelExists($ownRank);
        $this->assertSame('Officer', $otherRank->fresh()->name);
        $this->assertNotSame($gameVersion->id, $otherRank->fresh()->game_version_id);
    }

    #[Test]
    public function it_rejects_more_than_ten_guild_ranks(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $ranks = collect(range(1, 11))->map(fn (int $number): array => ['id' => null, 'name' => "Rank {$number}", 'count_attendance' => true])->all();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), ['guild_ranks' => $ranks]);

        $response->assertSessionHasErrors(['guild_ranks' => 'A guild can have up to 10 ranks.']);
        $this->assertSame(0, $gameVersion->guildRanks()->count());
    }

    #[Test]
    public function it_rejects_a_guild_rank_without_a_name(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                'guild_ranks' => [['id' => null, 'name' => '', 'count_attendance' => true]],
            ]);

        $response->assertSessionHasErrors(['guild_ranks.0.name' => 'Enter a name for every rank.']);
    }

    #[Test]
    public function it_accepts_optional_fields_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => null]);
        $nulls = ['faction' => null, 'color' => null, 'warcraft_logs_guild_id' => null];
        $this->expectUpdate($gameVersion, $nulls);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), $nulls);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_clearing_a_color_once_it_is_set(): void
    {
        $gameVersion = GameVersion::factory()->create(['color' => '#8B7ED0']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['color' => null]);

        $response->assertInvalid(['color' => "The colour can't be removed once it has been set."]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_title_or_release_date_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), [
                'title' => null,
                'release_date' => null,
            ]);

        $response->assertInvalid([
            'title' => 'The game version title is required.',
            'release_date' => 'The release date is required.',
        ]);
    }

    #[Test]
    public function it_accepts_an_update_that_changes_only_the_guild_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['guild_name' => 'Sister Guild']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['guild_name' => 'Sister Guild']);

        $response->assertValid();
        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Group('validation')]
    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[Test]
    public function it_rejects_a_guild_name_cleared_on_update(?string $guildName): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['guild_name' => $guildName]);

        $response->assertInvalid(['guild_name' => 'The guild name is required.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_guild_name_longer_than_twenty_four_characters_on_update(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['guild_name' => str_repeat('a', 25)]);

        $response->assertInvalid(['guild_name' => 'The guild name field must not be greater than 24 characters.']);
    }

    #[Test]
    public function it_updates_whether_characters_have_surnames(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['uses_surnames' => true]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['uses_surnames' => true]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_surnames_flag_that_is_not_true_or_false_on_update(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['uses_surnames' => 'sometimes']);

        $response->assertInvalid(['uses_surnames' => 'The surnames field must be true or false.']);
    }

    #[Group('validation')]
    #[Test]
    #[TestWith(['wrath'])]
    #[TestWith([''])]
    #[TestWith(['!!!'])]
    #[TestWith([null])]
    public function it_rejects_a_slug_on_update(?string $slug): void
    {
        $gameVersion = GameVersion::factory()->create(['slug' => 'tbc']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['slug' => $slug]);

        $response->assertInvalid(['slug' => 'The slug can\'t be changed once the game version is created.']);
        $this->assertSame('tbc', $gameVersion->fresh()->slug);
    }

    // ==================== update: races and classes ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_ticked_races_and_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $race = PlayableRace::factory()->create();
        $class = PlayableClass::factory()->create();
        $payload = ['playable_race_ids' => [$race->id], 'playable_class_ids' => [$class->id]];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', "Saved changes to {$gameVersion->title}.");
    }

    #[Test]
    public function it_accepts_empty_race_and_class_lists(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $payload = ['playable_race_ids' => [], 'playable_class_ids' => []];
        $this->expectUpdate($gameVersion, $payload);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), $payload);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_race_that_does_not_exist(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'playable_race_ids' => [999999],
            ]);

        $response->assertInvalid([
            'playable_race_ids.0' => 'One of the selected races no longer exists. Reload the page and try again.',
        ]);
    }

    // ==================== update: phases ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_ticked_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();
        $this->expectUpdate($gameVersion, ['phase_ids' => [$phase->id]]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [$phase->id]]);

        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Test]
    public function it_accepts_an_empty_phase_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['phase_ids' => []]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['phase_ids' => []]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_phase_list_sent_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['phase_ids' => null]);

        $response->assertInvalid(['phase_ids' => 'The phase ids field must be an array.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_phase_that_does_not_exist(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [999999]]);

        $response->assertInvalid([
            'phase_ids.0' => 'One of the selected phases no longer exists. Reload the page and try again.',
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_ticking_two_phases_with_the_same_number(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $owned = Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        $incoming = Phase::factory()->for(GameVersion::factory())->create(['number' => '1.0']);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [$owned->id, $incoming->id]]);

        $response->assertInvalid(['phase_ids' => 'Two of the ticked phases have the same number. Untick one, or change its number first.']);
    }

    #[Test]
    public function it_allows_swapping_a_phase_for_another_with_the_same_number(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        $incoming = Phase::factory()->for(GameVersion::factory())->create(['number' => '1.0']);
        $this->expectUpdate($gameVersion, ['phase_ids' => [$incoming->id]]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['phase_ids' => [$incoming->id]]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_unticking_a_phase_whose_number_a_phase_without_a_game_version_uses(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        Phase::factory()->create(['number' => '1.0', 'game_version_id' => null]);
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patchJson(route('management.game-versions.update', $gameVersion), ['phase_ids' => []]);

        $response->assertInvalid(['phase_ids' => 'Unticking Phase 1 would leave two phases without a game version numbered 1. Change its number first.']);
    }

    // ==================== update: unscheduled phase order ====================

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_order_of_the_unscheduled_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        $this->expectUpdate($gameVersion, ['unscheduled_phase_ids' => [$second->id, $first->id]]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'unscheduled_phase_ids' => [$second->id, $first->id],
            ]);

        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unscheduled_phase_order_that_leaves_one_out(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'unscheduled_phase_ids' => [$first->id],
            ]);

        $response->assertInvalid([
            'unscheduled_phase_ids' => 'The unscheduled phases have changed since this page loaded. Reload the page and try again.',
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unscheduled_phase_order_with_another_game_versions_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $own = Phase::factory()->for($gameVersion)->unscheduled()->create();
        $other = Phase::factory()->for(GameVersion::factory())->unscheduled()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'unscheduled_phase_ids' => [$own->id, $other->id],
            ]);

        $response->assertInvalid([
            'unscheduled_phase_ids' => 'The unscheduled phases have changed since this page loaded. Reload the page and try again.',
        ]);
    }

    // ==================== update: raids are read-only ====================

    #[Group('validation')]
    #[Test]
    public function it_ignores_a_raid_ids_field_since_raids_are_not_directly_assignable(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->create();
        // expectUpdate() asserts the *validated* payload passed to UpdateGameVersion::run().
        // raid_ids is no longer a recognized rule, so FormRequest::validated() strips it
        // and the action receives an empty array, not the raid_ids the client sent.
        $this->expectUpdate($gameVersion, []);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), ['raid_ids' => [$raid->id]]);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertValid();
    }

    // ==================== update: removed fields ====================

    #[Test]
    public function it_ignores_guild_tag_ids_sent_by_a_stale_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'realm' => 'Firemaw',
                'guild_tag_ids' => [999999],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Test]
    public function it_ignores_a_new_phase_sent_by_a_stale_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'realm' => 'Firemaw',
                'new_phase' => ['number' => '2', 'description' => 'Second'],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect($this->editUrl($gameVersion));
    }

    #[Test]
    public function it_ignores_a_new_raid_sent_by_a_stale_page(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        $this->expectUpdate($gameVersion, ['realm' => 'Firemaw']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'realm' => 'Firemaw',
                'new_raid' => ['name' => 'Karazhan', 'difficulty' => 'Normal', 'phase_id' => $phase->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect($this->editUrl($gameVersion));
    }

    // ==================== update: images ====================

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    #[TestWith([GameVersion::MEDIA_BANNER])]
    #[TestWith([GameVersion::MEDIA_ICON])]
    #[TestWith([GameVersion::MEDIA_LOGO])]
    public function it_stores_an_image_uploaded_as_a_spoofed_patch(string $collection): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create(['title' => 'Era']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->post(route('management.game-versions.update', $gameVersion), [
                '_method' => 'PATCH',
                $collection => UploadedFile::fake()->image("{$collection}.webp"),
            ]);

        $response->assertRedirect($this->editUrl($gameVersion));
        $response->assertSessionHas('success', 'Saved changes to Era.');
        $this->assertSame("{$collection}.webp", $gameVersion->fresh()->getFirstMedia($collection)?->file_name);
    }

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    public function it_stores_an_svg_icon(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($gameVersion))
            ->post(route('management.game-versions.update', $gameVersion), [
                '_method' => 'PATCH',
                GameVersion::MEDIA_ICON => $this->fakeSvg('icon.svg'),
            ]);

        $response->assertValid();
        $this->assertSame('icon.svg', $gameVersion->fresh()->getFirstMedia(GameVersion::MEDIA_ICON)?->file_name);
    }

    #[Test]
    public function it_passes_an_image_removed_as_null_to_the_update(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->expectUpdate($gameVersion, [GameVersion::MEDIA_BANNER => null]);

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [GameVersion::MEDIA_BANNER => null]);

        $response->assertValid();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_upload_that_is_not_an_image(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                GameVersion::MEDIA_LOGO => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ]);

        $response->assertInvalid([GameVersion::MEDIA_LOGO => 'The logo must be a JPEG, PNG or WEBP image.']);
    }

    #[Group('validation')]
    #[Test]
    #[TestWith([GameVersion::MEDIA_BANNER])]
    #[TestWith([GameVersion::MEDIA_LOGO])]
    public function it_rejects_an_svg_for_a_collection_that_does_not_accept_one(string $collection): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                $collection => $this->fakeSvg("{$collection}.svg"),
            ]);

        $response->assertInvalid([$collection => "The {$collection} must be a JPEG, PNG or WEBP image."]);
    }

    #[Group('validation')]
    #[Test]
    public function it_names_svg_among_the_accepted_types_when_rejecting_an_icon(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                GameVersion::MEDIA_ICON => UploadedFile::fake()->create('icon.pdf', 10, 'application/pdf'),
            ]);

        $response->assertInvalid([GameVersion::MEDIA_ICON => 'The icon must be a JPEG, PNG, WEBP or SVG image.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_image_larger_than_two_megabytes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        UpdateGameVersion::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->patch(route('management.game-versions.update', $gameVersion), [
                GameVersion::MEDIA_BANNER => UploadedFile::fake()->image('banner.png')->size(2049),
            ]);

        $response->assertInvalid([GameVersion::MEDIA_BANNER => 'The banner must be 2 MB or smaller.']);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_flushes_each_cache_once_for_an_update_with_images_and_phases(): void
    {
        Queue::fake();
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create();
        $phases = Phase::factory()->count(2)->for($gameVersion)->create();
        Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->put('canary', true, 60);
        Cache::tags(['raiding', 'events'])->put('canary', true, 60);
        $flushes = 0;
        Event::listen(CacheFlushed::class, function () use (&$flushes): void {
            $flushes++;
        });

        $this->actingAs($this->officer)
            ->from(route('management.game-versions.edit', $gameVersion))
            ->patch(route('management.game-versions.update', $gameVersion), [
                'title' => 'Renamed',
                'phase_ids' => $phases->modelKeys(),
                GameVersion::MEDIA_BANNER => UploadedFile::fake()->image('banner.png'),
                GameVersion::MEDIA_LOGO => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $flushes);
        $this->assertFalse(Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->has('canary'));
        $this->assertFalse(Cache::tags(['raiding', 'events'])->has('canary'));
    }

    // ==================== destroy ====================

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_a_game_version_that_is_not_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Unused']);

        $response = $this->actingAs($this->officer)->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('success', 'Deleted Unused.');
        $this->assertModelMissing($gameVersion);
    }

    #[Test]
    public function it_refuses_to_delete_a_game_version_that_is_in_use(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Live']);
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.game-versions.index'))
            ->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertRedirect(route('management.game-versions.index'));
        $response->assertSessionHas('error', 'Live is still linked to other records and cannot be deleted.');
        $this->assertModelExists($gameVersion);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_destroy_without_edit_datasets_permission(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $dashboardOnlyUser = User::factory()->withPermissions('view-officer-dashboard')->create();

        $response = $this->actingAs($dashboardOnlyUser)->delete(route('management.game-versions.destroy', $gameVersion));

        $response->assertForbidden();
        $this->assertModelExists($gameVersion);
    }

    // ==================== helpers ====================

    /**
     * Build a valid create payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Wrath Classic',
            'slug' => 'wrath',
            'realm' => 'Gehennas',
            'guild_name' => 'the Old Guard',
            'uses_surnames' => false,
            'faction' => Faction::HORDE->value,
            'release_date' => '2022-09-26',
            'color' => '#8B7ED0',
            'blizzard_namespace' => BlizzardNamespace::CLASSIC->value,
            'warcraft_logs_guild_id' => null,
            ...$overrides,
        ];
    }

    /**
     * Build a valid update payload: the create payload without the slug,
     * which can't be changed once the game version is created.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUpdatePayload(array $overrides = []): array
    {
        return Arr::except($this->validPayload($overrides), 'slug');
    }

    private function editUrl(GameVersion $gameVersion): string
    {
        return route('management.game-versions.edit', $gameVersion);
    }

    /**
     * A small SVG upload, with real markup so its MIME type is detected as image/svg+xml.
     */
    private function fakeSvg(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect width="16" height="16"/></svg>',
        );
    }

    /**
     * Fake UpdateGameVersion and expect it to run once, for the given game
     * version, with exactly the given validated data. equalTo() ignores key
     * order but not key presence, so an absent key and a null key differ.
     *
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(GameVersion $gameVersion, array $data): void
    {
        UpdateGameVersion::shouldRun()
            ->once()
            ->with($this->isGameVersion($gameVersion), $this->equalTo($data));
    }

    /**
     * Match the route-bound copy of the given game version in a faked action's
     * arguments. Route binding loads a fresh instance, so identity won't do.
     */
    private function isGameVersion(GameVersion $gameVersion): MatcherInterface
    {
        return Mockery::on(fn (GameVersion $given): bool => $given->is($gameVersion));
    }

    /**
     * Fake BuildGameVersionRelationships for the given game version and return
     * the stub checklist data it hands back, for the test to find in the props.
     *
     * @return array<string, array{options: list<array<string, mixed>>, selected_ids: list<int>}>
     */
    private function fakeRelationships(GameVersion $gameVersion): array
    {
        $relationships = ['phases' => ['data' => [], 'selected_ids' => [7]]];

        BuildGameVersionRelationships::shouldRun()
            ->once()
            ->with($this->isGameVersion($gameVersion))
            ->andReturn($relationships);

        return $relationships;
    }
}
