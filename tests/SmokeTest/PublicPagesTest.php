<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_loads(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function roster_page_loads(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetPlayableRaceIndexRequest::class => MockResponse::make(body: ['races' => []], status: 200),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [],
            ], status: 200),
        ]);

        $response = $this->get(route('characters.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function character_show_page_loads(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();

        $response = $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $character->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function battlenet_usage_page_loads(): void
    {
        $response = $this->get(route('battlenet-usage'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function privacy_policy_page_loads(): void
    {
        $response = $this->get(route('privacypolicy'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_page_loads(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
