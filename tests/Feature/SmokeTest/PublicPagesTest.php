<?php

namespace Tests\Feature\SmokeTest;

use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->instance(
            GuildService::class,
            Mockery::mock(GuildService::class, function (MockInterface $mock) {
                $mock->shouldReceive('members')->andReturn(collect());
            })
        );

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_roster_page_loads(): void
    {
        $this->instance(
            PlayableClassService::class,
            Mockery::mock(PlayableClassService::class, function (MockInterface $mock) {
                $mock->shouldReceive('index')->andReturn(['classes' => []]);
            })
        );

        $this->instance(
            PlayableRaceService::class,
            Mockery::mock(PlayableRaceService::class, function (MockInterface $mock) {
                $mock->shouldReceive('index')->andReturn(['races' => []]);
            })
        );

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_comps_page_redirects(): void
    {
        $response = $this->get('/comps');

        $response->assertStatus(303);
        $response->assertRedirect();
    }

    public function test_battlenet_usage_page_loads(): void
    {
        $response = $this->get('/info/battlenet-usage');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_privacy_policy_page_loads(): void
    {
        $response = $this->get('/info/privacy');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
