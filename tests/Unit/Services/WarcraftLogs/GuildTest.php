<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Data\Faction;
use App\Services\WarcraftLogs\Data\Server;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\Guild;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuildTest extends TestCase
{
    protected function fakeSuccessfulGuildResponse(array $guildData): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'guildData' => [
                        'guild' => $guildData,
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function sampleGuildData(): array
    {
        return [
            'id' => 774848,
            'name' => 'Regrowth',
            'faction' => [
                'id' => 1,
                'name' => 'Alliance',
            ],
            'server' => [
                'id' => 1234,
                'name' => 'Pyrewood Village',
                'slug' => 'pyrewood-village',
                'region' => [
                    'id' => 1,
                    'name' => 'EU',
                    'slug' => 'eu',
                ],
            ],
        ];
    }

    protected function makeConfig(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
            'graphql_url' => 'https://www.warcraftlogs.com/api/v2/client',
            'guild_id' => 774848,
            'timeout' => 30,
            'cache_ttl' => 43200,
        ], $overrides);
    }

    protected function makeGuild(array $configOverrides = []): Guild
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $config = $this->makeConfig($configOverrides);
        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new Guild($config, $auth);
    }

    public function test_constructor_fetches_guild_data(): void
    {
        $this->makeGuild();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['id']) && $body['variables']['id'] === 774848;
        });
    }

    public function test_constructor_uses_configured_guild_id(): void
    {
        $this->makeGuild(['guild_id' => 774848]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['variables']['id'] === 774848;
        });
    }

    public function test_constructor_throws_exception_when_guild_not_found(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'guildData' => [
                        'guild' => null,
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $this->expectException(GuildNotFoundException::class);
        $this->expectExceptionMessage('Guild with ID 99999 not found');

        $config = $this->makeConfig(['guild_id' => 99999]);
        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        new Guild($config, $auth);
    }

    public function test_constructor_caches_graphql_response(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::type('int'), \Mockery::type('callable'))
            ->andReturn([
                'guildData' => [
                    'guild' => $this->sampleGuildData(),
                ],
            ]);

        $config = $this->makeConfig();
        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        new Guild($config, $auth);
    }

    public function test_id_property_returns_guild_id(): void
    {
        $guild = $this->makeGuild();

        $this->assertEquals(774848, $guild->id);
    }

    public function test_name_property_returns_guild_name(): void
    {
        $guild = $this->makeGuild();

        $this->assertEquals('Regrowth', $guild->name);
    }

    public function test_server_property_returns_server_object(): void
    {
        $guild = $this->makeGuild();

        $this->assertInstanceOf(Server::class, $guild->server);
        $this->assertEquals('Pyrewood Village', $guild->server->name);
        $this->assertEquals('pyrewood-village', $guild->server->slug);
        $this->assertEquals('EU', $guild->server->region->name);
    }

    public function test_faction_property_returns_faction_object(): void
    {
        $guild = $this->makeGuild();

        $this->assertInstanceOf(Faction::class, $guild->faction);
        $this->assertEquals(1, $guild->faction->id);
        $this->assertEquals('Alliance', $guild->faction->name);
    }

    public function test_accessing_invalid_property_throws_exception(): void
    {
        $guild = $this->makeGuild();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Property invalid does not exist');

        $guild->invalid;
    }

    public function test_guild_service_can_be_resolved_from_container(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = app(Guild::class);

        $this->assertInstanceOf(Guild::class, $service);
    }

    public function test_to_array_returns_array_with_all_properties(): void
    {
        $guild = $this->makeGuild();

        $array = $guild->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('server', $array);
        $this->assertArrayHasKey('faction', $array);
    }

    public function test_to_array_returns_correct_values(): void
    {
        $guild = $this->makeGuild();

        $array = $guild->toArray();

        $this->assertEquals(774848, $array['id']);
        $this->assertEquals('Regrowth', $array['name']);
    }

    public function test_to_array_includes_nested_server_data(): void
    {
        $guild = $this->makeGuild();

        $array = $guild->toArray();

        $this->assertIsArray($array['server']);
        $this->assertEquals(1234, $array['server']['id']);
        $this->assertEquals('Pyrewood Village', $array['server']['name']);
        $this->assertEquals('pyrewood-village', $array['server']['slug']);
        $this->assertArrayHasKey('region', $array['server']);
        $this->assertEquals('EU', $array['server']['region']['name']);
    }

    public function test_to_array_includes_nested_faction_data(): void
    {
        $guild = $this->makeGuild();

        $array = $guild->toArray();

        $this->assertIsArray($array['faction']);
        $this->assertEquals(1, $array['faction']['id']);
        $this->assertEquals('Alliance', $array['faction']['name']);
    }
}
