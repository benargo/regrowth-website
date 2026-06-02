<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
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
        $response = $this->get('/');

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

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function comps_page_redirects(): void
    {
        $response = $this->get('/comps');

        $response->assertStatus(303);
        $response->assertRedirect();
    }

    #[Test]
    public function battlenet_usage_page_loads(): void
    {
        $response = $this->get('/info/battlenet-usage');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function privacy_policy_page_loads(): void
    {
        $response = $this->get('/info/privacy');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function daily_quests_page_loads(): void
    {
        $response = $this->get('/daily-quests');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
