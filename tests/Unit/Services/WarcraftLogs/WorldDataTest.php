<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\ValueObjects\Expansion;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use App\Services\WarcraftLogs\WorldData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorldDataTest extends TestCase
{
    protected function getService(): WorldData
    {
        $config = [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'guild_id' => 774848,
        ];

        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new WorldData($config, $auth);
    }

    protected function fakeGraphqlResponse(array $data, int $status = 200): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::response($data, $status),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function fakeNotRateLimited(): void
    {
        Cache::shouldReceive('has')
            ->with('warcraftlogs:rate_limited')
            ->andReturn(false);
    }

    protected function fakeRemember(): void
    {
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });
    }

    // --- getExpansions ---

    #[Test]
    public function get_expansions_returns_expansion_value_objects(): void
    {
        $this->fakeGraphqlResponse([
            'data' => [
                'worldData' => [
                    'expansions' => [
                        [
                            'id' => 1,
                            'name' => 'Classic',
                            'zones' => [
                                ['id' => 10, 'name' => 'Molten Core'],
                            ],
                        ],
                        [
                            'id' => 2,
                            'name' => 'The Burning Crusade',
                            'zones' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $service = $this->getService();
        $expansions = $service->getExpansions();

        $this->assertCount(2, $expansions);
        $this->assertContainsOnlyInstancesOf(Expansion::class, $expansions);
        $this->assertEquals(1, $expansions[0]->id);
        $this->assertEquals('Classic', $expansions[0]->name);
        $this->assertCount(1, $expansions[0]->zones);
        $this->assertInstanceOf(Zone::class, $expansions[0]->zones[0]);
        $this->assertEquals(10, $expansions[0]->zones[0]->id);
        $this->assertEquals('Molten Core', $expansions[0]->zones[0]->name);
    }

    #[Test]
    public function get_expansions_returns_empty_array_when_no_expansions(): void
    {
        $this->fakeGraphqlResponse([
            'data' => [
                'worldData' => [
                    'expansions' => [],
                ],
            ],
        ]);

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $expansions = $this->getService()->getExpansions();

        $this->assertSame([], $expansions);
    }

    #[Test]
    public function get_expansions_uses_in_memory_cache_on_second_call(): void
    {
        $this->fakeGraphqlResponse([
            'data' => [
                'worldData' => [
                    'expansions' => [
                        ['id' => 1, 'name' => 'Classic', 'zones' => []],
                    ],
                ],
            ],
        ]);

        $this->fakeNotRateLimited();

        // Remember is called once; second call returns from property cache
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $service->getExpansions();
        $service->getExpansions();
    }

    // --- getZones ---

    #[Test]
    public function get_zones_without_expansion_id_returns_all_zones(): void
    {
        $this->fakeGraphqlResponse([
            'data' => [
                'worldData' => [
                    'zones' => [
                        ['id' => 10, 'name' => 'Molten Core', 'frozen' => false],
                        ['id' => 11, 'name' => 'Blackwing Lair', 'frozen' => false],
                    ],
                ],
            ],
        ]);

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $zones = $this->getService()->getZones();

        $this->assertCount(2, $zones);
        $this->assertContainsOnlyInstancesOf(Zone::class, $zones);
        $this->assertEquals(10, $zones[0]->id);
        $this->assertEquals('Molten Core', $zones[0]->name);
    }

    #[Test]
    public function get_zones_with_valid_expansion_id_returns_zones_for_that_expansion(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push([
                    'data' => [
                        'worldData' => [
                            'expansions' => [
                                ['id' => 1, 'name' => 'Classic', 'zones' => []],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'worldData' => [
                            'zones' => [
                                ['id' => 10, 'name' => 'Molten Core', 'frozen' => false],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $zones = $this->getService()->getZones(1);

        $this->assertCount(1, $zones);
        $this->assertEquals(10, $zones[0]->id);
        $this->assertEquals('Molten Core', $zones[0]->name);
    }

    #[Test]
    public function get_zones_throws_for_invalid_expansion_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'worldData' => [
                        'expansions' => [
                            ['id' => 1, 'name' => 'Classic', 'zones' => []],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expansion ID 999 is not valid.');

        $this->getService()->getZones(999);
    }

    #[Test]
    public function get_zones_fetches_expansions_before_validating_expansion_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push([
                    'data' => [
                        'worldData' => [
                            'expansions' => [
                                ['id' => 5, 'name' => 'Cataclysm', 'zones' => []],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'worldData' => [
                            'zones' => [
                                ['id' => 50, 'name' => 'Dragon Soul', 'frozen' => false],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        // Expansions haven't been called yet — getZones should load them internally
        $zones = $this->getService()->getZones(5);

        $this->assertCount(1, $zones);
        $this->assertEquals('Dragon Soul', $zones[0]->name);
    }

    #[Test]
    public function get_zones_uses_in_memory_cache_on_second_call_with_same_expansion_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push([
                    'data' => [
                        'worldData' => [
                            'expansions' => [
                                ['id' => 1, 'name' => 'Classic', 'zones' => []],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'worldData' => [
                            'zones' => [
                                ['id' => 10, 'name' => 'Molten Core', 'frozen' => false],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        // Expansions query + zones query = 2 Cache::remember calls; third call (second getZones) is in-memory
        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $service->getZones(1);
        $service->getZones(1);
    }

    #[Test]
    public function get_zones_caches_different_expansion_ids_separately(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'fresh.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push([
                    'data' => [
                        'worldData' => [
                            'expansions' => [
                                ['id' => 1, 'name' => 'Classic', 'zones' => []],
                                ['id' => 2, 'name' => 'The Burning Crusade', 'zones' => []],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => ['worldData' => ['zones' => [['id' => 10, 'name' => 'Molten Core', 'frozen' => false]]]],
                ], 200)
                ->push([
                    'data' => ['worldData' => ['zones' => [['id' => 20, 'name' => 'Karazhan', 'frozen' => false]]]],
                ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();
        $this->fakeRemember();

        $service = $this->getService();
        $zonesForExpansion1 = $service->getZones(1);
        $zonesForExpansion2 = $service->getZones(2);

        $this->assertEquals('Molten Core', $zonesForExpansion1[0]->name);
        $this->assertEquals('Karazhan', $zonesForExpansion2[0]->name);
    }
}
