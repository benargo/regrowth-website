<?php

namespace Tests\Feature\Characters;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class RosterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->gameVersion = GameVersion::factory()->fetchableRoster()->create(['slug' => 'tbc']);
    }

    #[Test]
    public function index_is_accessible_without_authentication(): void
    {
        $response = $this->get(route('roster.index', $this->gameVersion));

        $response->assertOk();
    }

    #[Test]
    public function index_does_not_pass_a_filters_prop(): void
    {
        // Filters are persisted client-side in localStorage, so the server no
        // longer round-trips a filters prop or reads filter query parameters.
        $response = $this->get(route('roster.index', [
            'gameVersion' => $this->gameVersion,
            'filter[search]' => 'Ozona',
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->missing('filters')
        );
    }

    #[Test]
    public function index_renders_with_characters(): void
    {
        $this->fakeRosterWithMembers([
            [
                'character' => [
                    'id' => 1,
                    'name' => 'Thrall',
                    'level' => 60,
                    'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                    'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Shaman', 'id' => 1],
                    'playable_race' => ['key' => ['href' => 'https://example.test/race/2'], 'name' => 'Orc', 'id' => 2],
                ],
                'rank' => 0,
            ],
        ]);

        $response = $this->get(route('roster.index', $this->gameVersion));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->missing('characters')
            ->has('classes')
            ->has('ranks')
            ->has('races')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('characters', 1))
        );
    }

    #[Test]
    public function index_characters_deferred_prop_sets_is_known_based_on_database(): void
    {
        Character::factory()->create(['id' => 52461508, 'is_main' => true]);

        $this->fakeRosterWithMembers([
            [
                'character' => [
                    'id' => 52461508,
                    'name' => 'Ozona',
                    'level' => 60,
                    'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                    'playable_class' => ['key' => ['href' => 'https://example.test/class/8'], 'name' => 'Mage', 'id' => 8],
                    'playable_race' => ['key' => ['href' => 'https://example.test/race/7'], 'name' => 'Gnome', 'id' => 7],
                ],
                'rank' => 9,
            ],
            [
                'character' => [
                    'id' => 99999999,
                    'name' => 'Unknown',
                    'level' => 60,
                    'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                    'playable_class' => ['key' => ['href' => 'https://example.test/class/8'], 'name' => 'Mage', 'id' => 8],
                    'playable_race' => ['key' => ['href' => 'https://example.test/race/7'], 'name' => 'Gnome', 'id' => 7],
                ],
                'rank' => 9,
            ],
        ]);

        $response = $this->get(route('roster.index', $this->gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters', 2)
                ->where('characters.0.character.is_known', true)
                ->where('characters.0.character.is_main', true)
                ->where('characters.1.character.is_known', false)
                ->where('characters.1.character.is_main', false)
            )
        );
    }

    #[Test]
    public function index_characters_deferred_prop_returns_flat_ids_without_realm(): void
    {
        GuildRank::factory()->create(['game_version_id' => $this->gameVersion->id, 'sort_order' => 9, 'name' => 'Warden']);

        $this->fakeRosterWithMembers([
            [
                'character' => [
                    'id' => 52461508,
                    'name' => 'Ozona',
                    'level' => 60,
                    'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                    'playable_class' => ['key' => ['href' => 'https://example.test/class/8'], 'name' => 'Mage', 'id' => 8],
                    'playable_race' => ['key' => ['href' => 'https://example.test/race/7'], 'name' => 'Gnome', 'id' => 7],
                ],
                'rank' => 9,
            ],
        ]);

        $response = $this->get(route('roster.index', $this->gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters', 1)
                ->where('characters.0.rank', 'Warden')
                ->where('characters.0.character.id', 52461508)
                ->where('characters.0.character.name', 'Ozona')
                ->where('characters.0.character.slug', 'ozona')
                ->where('characters.0.character.level', 60)
                ->where('characters.0.character.playable_class_id', 8)
                ->where('characters.0.character.playable_race_id', 7)
                ->missing('characters.0.character.realm')
                ->missing('characters.0.character.playable_class')
                ->missing('characters.0.character.playable_race')
            )
        );
    }

    #[Test]
    public function index_passes_ranks_prop_as_deduplicated_names(): void
    {
        GuildRank::factory()->create(['game_version_id' => $this->gameVersion->id, 'sort_order' => 0, 'name' => 'Guild Master']);
        GuildRank::factory()->create(['game_version_id' => $this->gameVersion->id, 'sort_order' => 1, 'name' => 'Officer']);
        GuildRank::factory()->create(['game_version_id' => $this->gameVersion->id, 'sort_order' => 2, 'name' => 'Officer']);

        $response = $this->get(route('roster.index', $this->gameVersion));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->where('ranks', ['Guild Master', 'Officer'])
        );
    }

    // ==================== redirectToDefaultRoster ====================

    #[Test]
    public function legacy_roster_url_redirects_to_the_default_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => Carbon::now()->subDay()]);

        $this->get(route('characters.index'))->assertRedirect(route('roster.index', 'newer'))->assertStatus(303);
    }

    #[Test]
    public function legacy_roster_url_is_not_found_when_no_game_version_has_a_roster(): void
    {
        GameVersion::query()->delete();

        $this->get(route('characters.index'))->assertNotFound();
    }

    // ==================== index — game version ====================

    #[Test]
    public function unknown_game_version_slug_is_not_found(): void
    {
        $this->get('/no-such-version/roster')->assertNotFound();
    }

    #[Test]
    public function game_version_without_a_current_roster_is_not_found(): void
    {
        $unreleased = GameVersion::factory()->fetchableRoster()->create(['slug' => 'later', 'release_date' => Carbon::now()->addMonth()]);

        $this->get(route('roster.index', $unreleased))->assertNotFound();
    }

    #[Test]
    public function roster_request_uses_the_game_versions_realm_guild_and_namespace(): void
    {
        $this->gameVersion->update(['realm' => 'Living Flame', 'guild_name' => 'Wild Growth', 'blizzard_namespace' => BlizzardNamespace::ERA]);
        $this->fakeRosterWithMembers([]);

        $this->get(route('roster.index', $this->gameVersion))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('characters', 0))
        );

        Saloon::assertSent(fn ($request, $response) => $request instanceof GetGuildRosterRequest
            && $request->resolveEndpoint() === '/data/wow/guild/living-flame/wild-growth/roster'
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === BlizzardNamespace::ERA->forProfileRequests(Region::from(config('services.blizzard.region'))));
    }

    #[Test]
    public function props_are_scoped_to_the_game_version(): void
    {
        $sister = GameVersion::factory()->fetchableRoster()->create(['slug' => 'sister']);
        GuildRank::factory()->create(['game_version_id' => $this->gameVersion->id, 'sort_order' => 0, 'name' => 'Guild Master']);
        GuildRank::factory()->create(['game_version_id' => $sister->id, 'sort_order' => 0, 'name' => 'Warchief']);
        $mage = PlayableClass::factory()->create();
        $this->gameVersion->playableClasses()->attach($mage);
        $horde = PlayableRace::factory()->create(['faction' => Faction::HORDE]);
        $this->gameVersion->playableRaces()->attach($horde);

        $this->get(route('roster.index', $this->gameVersion))->assertInertia(fn (Assert $page) => $page
            ->where('ranks', ['Guild Master'])
            ->has('classes', 1)
            ->has('races', 1)
            ->where('gameVersion.slug', 'tbc')
        );
    }

    #[Test]
    public function switcher_lists_every_current_roster_newest_first(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => Carbon::now()->subDay()]);

        $this->get(route('roster.index', $this->gameVersion))->assertInertia(fn (Assert $page) => $page
            ->has('gameVersions', 2)
            ->where('gameVersions.0.slug', 'newer')
            ->where('gameVersions.1.slug', 'tbc')
        );
    }

    // ==================== helpers ====================

    private function fakeRosterWithMembers(array $members): void
    {
        $this->mockGetGuildRoster(['members' => $members]);
        $this->applyBlizzardMocks();
    }
}
