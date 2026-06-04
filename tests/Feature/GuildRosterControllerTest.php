<?php

namespace Tests\Feature;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Models\PlayableClass;
use Database\Seeders\GuildRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class GuildRosterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GuildRankSeeder::class);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: [
                'races' => [
                    ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                    ['key' => ['href' => 'https://example.test/race/3'], 'name' => 'Dwarf', 'id' => 3],
                    ['key' => ['href' => 'https://example.test/race/4'], 'name' => 'Night Elf', 'id' => 4],
                    ['key' => ['href' => 'https://example.test/race/7'], 'name' => 'Gnome', 'id' => 7],
                    ['key' => ['href' => 'https://example.test/race/11'], 'name' => 'Draenei', 'id' => 11],
                ],
            ], status: 200),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [],
            ], status: 200),
        ]);
    }

    #[Test]
    public function it_renders_roster_page(): void
    {
        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster')
            ->has('classes')
            ->has('races')
            ->has('ranks')
            ->has('level_cap')
        );
    }

    #[Test]
    public function it_passes_classes_with_icon_url_to_the_view(): void
    {
        PlayableClass::factory()->count(2)->create();

        $response = $this->get(route('roster'));

        $response->assertInertia(fn (Assert $page) => $page->component('Roster')
            ->has('classes', 2, fn (Assert $class) => $class->hasAll(['id', 'name', 'slug', 'icon_url'])
            )
        );
    }

    #[Test]
    public function it_loads_members_via_deferred_prop(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: [
                'races' => [],
            ], status: 200),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [
                    [
                        'character' => [
                            'id' => 12345,
                            'name' => 'Thrall',
                            'level' => 70,
                            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                            'playable_class' => ['key' => ['href' => 'https://example.test/class/7'], 'name' => 'Shaman', 'id' => 7],
                            'playable_race' => ['key' => ['href' => 'https://example.test/race/2'], 'name' => 'Orc', 'id' => 2],
                        ],
                        'rank' => 3,
                    ],
                ],
            ], status: 200),
        ]);

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster')
            ->missing('members') // Deferred prop
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('members', 1)
                ->where('members.0.character.name', 'Thrall')
                ->where('members.0.character.level', 70)
            )
        );
    }

    #[Test]
    public function it_enriches_members_with_playable_class_and_race(): void
    {
        PlayableClass::factory()->create(['id' => 8, 'name' => 'Mage']);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: [
                'races' => [],
            ], status: 200),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [
                    [
                        'character' => [
                            'id' => 1,
                            'name' => 'Jaina',
                            'level' => 70,
                            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1],
                            'playable_class' => ['key' => ['href' => 'https://example.test/class/8'], 'name' => 'Mage', 'id' => 8],
                            'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                        ],
                        'rank' => 0,
                    ],
                ],
            ], status: 200),
        ]);

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('members')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('members', 1)
                ->where('members.0.character.playable_class.id', 8)
                ->where('members.0.character.playable_class.name', 'Mage')
                ->where('members.0.character.playable_race.id', 1)
                ->where('members.0.character.playable_race.name', 'Human')
                ->has('members.0.rank.id')
                ->has('members.0.rank.position')
                ->has('members.0.rank.name')
            )
        );
    }
}
