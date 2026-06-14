<?php

namespace Tests\Feature\Characters;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Blizzard\HasBlizzardTokenMock;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class RosterTest extends TestCase
{
    use HasBlizzardTokenMock;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function index_is_accessible_without_authentication(): void
    {
        $response = $this->get(route('characters.index'));

        $response->assertOk();
    }

    #[Test]
    public function index_does_not_pass_a_filters_prop(): void
    {
        // Filters are persisted client-side in localStorage, so the server no
        // longer round-trips a filters prop or reads filter query parameters.
        $response = $this->get(route('characters.index', [
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

        $response = $this->get(route('characters.index'));

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

        $response = $this->get(route('characters.index'));

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
        GuildRank::factory()->create(['position' => 9, 'name' => 'Warden']);

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

        $response = $this->get(route('characters.index'));

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
        GuildRank::factory()->create(['position' => 0, 'name' => 'Guild Master']);
        GuildRank::factory()->create(['position' => 1, 'name' => 'Officer']);
        GuildRank::factory()->create(['position' => 2, 'name' => 'Officer']);

        $response = $this->get(route('characters.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Index')
            ->where('ranks', ['Guild Master', 'Officer'])
        );
    }

    private function fakeRosterWithMembers(array $members): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => $members,
            ], status: 200),
        ]);
    }
}
