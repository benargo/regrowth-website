<?php

namespace Tests\SmokeTest;

use App\Services\Blizzard\BlizzardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
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
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClasses')->andReturn(['classes' => []]);
            $mock->shouldReceive('getPlayableRaces')->andReturn(['races' => []]);
            $mock->shouldReceive('getGuildRoster')->andReturn(['members' => []]);
            $mock->shouldReceive('findPlayableClass')->andReturn([]);
            $mock->shouldReceive('findPlayableRace')->andReturn([]);
            $mock->shouldReceive('getPlayableClassMedia')->andReturn(['assets' => []]);
        });

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
