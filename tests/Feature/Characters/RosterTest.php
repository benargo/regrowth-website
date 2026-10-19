<?php

namespace Tests\Feature\Characters;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
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
        $response = $this->get(route('roster.index'));

        $response->assertOk();
    }

    #[Test]
    public function index_does_not_pass_a_filters_prop(): void
    {
        // Filters are persisted client-side in localStorage, so the server no
        // longer round-trips a filters prop or reads filter query parameters.
        $response = $this->get(route('roster.index', [
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

        $response = $this->get(route('roster.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->missing('characters')
            ->has('classes.data')
            ->has('ranks')
            ->has('races.data')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('characters.data', 1))
        );
    }

    #[Test]
    public function index_characters_deferred_prop_sets_is_known_based_on_database(): void
    {
        Character::factory()->for($this->gameVersion)->create(['id' => 52461508, 'is_main' => true]);

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

        $response = $this->get(route('roster.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters.data', 2)
                ->where('characters.data.0.character.is_known', true)
                ->where('characters.data.0.character.is_main', true)
                ->where('characters.data.1.character.is_known', false)
                ->where('characters.data.1.character.is_main', false)
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

        $response = $this->get(route('roster.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters.data', 1)
                ->where('characters.data.0.rank', 'Warden')
                ->where('characters.data.0.character.id', 52461508)
                ->where('characters.data.0.character.name', 'Ozona')
                ->where('characters.data.0.character.slug', 'ozona')
                ->where('characters.data.0.character.level', 60)
                ->where('characters.data.0.character.playable_class_id', 8)
                ->where('characters.data.0.character.playable_race_id', 7)
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

        $response = $this->get(route('roster.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->where('ranks', ['Guild Master', 'Officer'])
        );
    }

    // ==================== index — game version ====================

    #[Test]
    public function it_shows_the_default_roster_when_no_game_version_is_remembered(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => Carbon::now()->subDay()]);

        $this->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.slug', 'newer')
        );
    }

    #[Test]
    public function it_shows_the_remembered_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => Carbon::now()->subDay()]);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'tbc'])
            ->get(route('roster.index'))
            ->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'tbc'));
    }

    #[Test]
    public function it_shows_and_remembers_the_requested_game_version(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['slug' => 'newer', 'release_date' => Carbon::now()->subDay()]);

        $this->get(route('roster.index', ['game_version' => 'tbc']))
            ->assertRedirect(route('roster.index'))
            ->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, 'tbc');

        $this->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page->where('gameVersion.data.slug', 'tbc'));
    }

    #[Test]
    public function it_follows_the_session_version_outside_the_roster_list(): void
    {
        GameVersion::factory()->create(['slug' => 'later', 'release_date' => Carbon::now()->addMonth()]);

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'later'])
            ->get(route('roster.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Roster/Index')
                ->where('gameVersion.data.slug', 'later')
                ->where('hasRoster', false)
                ->missing('characters')
                ->missing('classes')
                ->missing('ranks')
                ->missing('races')
            );
    }

    #[Test]
    public function it_shows_the_roster_of_a_roster_version(): void
    {
        $this->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page
            ->where('gameVersion.data.slug', 'tbc')
            ->where('hasRoster', true)
            ->has('classes.data')
        );
    }

    #[Test]
    public function it_returns_404_only_when_there_are_no_game_versions(): void
    {
        $this->get(route('roster.index'))->assertOk();

        GameVersion::all()->each->delete();

        $this->get(route('roster.index'))->assertNotFound();
    }

    #[Test]
    #[Group('deprecated')]
    public function the_per_version_roster_url_is_gone(): void
    {
        $this->get('/tbc/roster')->assertNotFound();
    }

    #[Test]
    public function roster_request_uses_the_game_versions_realm_guild_and_namespace(): void
    {
        $this->gameVersion->update(['realm' => 'Living Flame', 'guild_name' => 'Wild Growth', 'blizzard_namespace' => BlizzardNamespace::ERA]);
        $this->fakeRosterWithMembers([]);

        $this->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('characters.data', 0))
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

        $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => 'tbc'])->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page
            ->where('ranks', ['Guild Master'])
            ->has('classes.data', 1)
            ->has('races.data', 1)
            ->where('gameVersion.data.slug', 'tbc')
        );
    }

    #[Test]
    public function the_shared_switcher_list_is_present_and_the_page_sends_no_own_list(): void
    {
        $this->get(route('roster.index'))->assertInertia(fn (Assert $page) => $page
            ->has('gameVersions.meta.current')
        );
    }

    // ==================== helpers ====================

    private function fakeRosterWithMembers(array $members): void
    {
        $this->mockGetGuildRoster(['members' => $members]);
        $this->applyBlizzardMocks();
    }
}
