<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\Data\Guild;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Services\WarcraftLogs\GuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuildServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getService(array $configOverrides = []): GuildService
    {
        $config = array_merge([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
            'graphql_url' => 'https://www.warcraftlogs.com/api/v2/client',
            'guild_id' => 774848,
            'timeout' => 30,
            'cache_ttl' => 3600,
        ], $configOverrides);

        return new GuildService($config);
    }

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
            'tags' => [
                ['id' => 1, 'name' => 'Raid Team'],
                ['id' => 2, 'name' => 'Main Roster'],
            ],
        ];
    }

    public function test_get_guild_returns_guild_data_object(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $guild = $service->getGuild();

        $this->assertInstanceOf(Guild::class, $guild);
        $this->assertEquals(774848, $guild->id);
        $this->assertEquals('Regrowth', $guild->name);
    }

    public function test_get_guild_uses_configured_guild_id(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService(['guild_id' => 774848]);
        $service->getGuild();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['id']) && $body['variables']['id'] === 774848;
        });
    }

    public function test_find_guild_returns_guild_with_server_data(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $guild = $service->findGuild(774848);

        $this->assertEquals('Pyrewood Village', $guild->server->name);
        $this->assertEquals('pyrewood-village', $guild->server->slug);
        $this->assertEquals('EU', $guild->server->region->name);
    }

    public function test_find_guild_returns_guild_with_faction_data(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $guild = $service->findGuild(774848);

        $this->assertEquals(1, $guild->faction->id);
        $this->assertEquals('Alliance', $guild->faction->name);
    }

    public function test_find_guild_returns_guild_with_tags_data(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $guild = $service->findGuild(774848);

        $this->assertCount(2, $guild->tags);
        $this->assertContainsOnlyInstancesOf(GuildTag::class, $guild->tags);
        $this->assertEquals(1, $guild->tags[0]->id);
        $this->assertEquals('Raid Team', $guild->tags[0]->name);
        $this->assertEquals(2, $guild->tags[1]->id);
        $this->assertEquals('Main Roster', $guild->tags[1]->name);
    }

    public function test_find_guild_throws_exception_when_guild_not_found(): void
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

        $service = $this->getService();

        $this->expectException(GuildNotFoundException::class);
        $this->expectExceptionMessage('Guild with ID 99999 not found');

        $service->findGuild(99999);
    }

    public function test_find_guild_throws_exception_on_graphql_error(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'Internal server error'],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();

        $this->expectException(GraphQLException::class);

        $service->findGuild(774848);
    }

    public function test_find_guild_converts_not_found_graphql_error_to_exception(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'Guild does not exist'],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();

        $this->expectException(GuildNotFoundException::class);

        $service->findGuild(99999);
    }

    public function test_find_guild_caches_results(): void
    {
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('remember')
            ->once()
            ->with(\Mockery::type('string'), 3600, \Mockery::type('callable'))
            ->andReturn([
                'guildData' => [
                    'guild' => $this->sampleGuildData(),
                ],
            ]);

        $service = $this->getService();
        $guild = $service->findGuild(774848);

        $this->assertInstanceOf(Guild::class, $guild);
    }

    public function test_fresh_bypasses_cache(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        $service = $this->getService();
        $guild = $service->fresh()->findGuild(774848);

        $this->assertInstanceOf(Guild::class, $guild);
        $this->assertEquals(774848, $guild->id);
    }

    public function test_guild_can_be_converted_to_array(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $guild = $service->findGuild(774848);

        $array = $guild->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('server', $array);
        $this->assertArrayHasKey('faction', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertEquals(774848, $array['id']);
        $this->assertCount(2, $array['tags']);
    }

    public function test_guild_service_can_be_resolved_from_container(): void
    {
        $service = app(GuildService::class);

        $this->assertInstanceOf(GuildService::class, $service);
    }

    public function test_get_guild_tags_returns_eloquent_collection(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $tags = $service->getGuildTags();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $tags);
    }

    public function test_get_guild_tags_returns_tags_from_api(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $tags = $service->getGuildTags();

        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(GuildTag::class, $tags);
        $this->assertEquals('Raid Team', $tags->firstWhere('id', 1)->name);
        $this->assertEquals('Main Roster', $tags->firstWhere('id', 2)->name);
    }

    public function test_get_guild_tags_syncs_tags_to_database(): void
    {
        $this->fakeSuccessfulGuildResponse($this->sampleGuildData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $service->getGuildTags();

        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 1, 'name' => 'Raid Team']);
        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 2, 'name' => 'Main Roster']);
    }

    public function test_get_guild_tags_falls_back_to_database_when_api_returns_no_tags(): void
    {
        // Pre-populate database with existing tags
        GuildTag::factory()->create(['id' => 10, 'name' => 'Database Tag']);

        $guildDataWithNoTags = $this->sampleGuildData();
        $guildDataWithNoTags['tags'] = [];
        $this->fakeSuccessfulGuildResponse($guildDataWithNoTags);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $tags = $service->getGuildTags();

        $this->assertCount(1, $tags);
        $this->assertEquals('Database Tag', $tags->first()->name);
    }

    public function test_get_guild_tags_returns_all_database_tags_when_api_returns_none(): void
    {
        // Pre-populate database with multiple existing tags
        GuildTag::factory()->create(['id' => 10, 'name' => 'Tag A']);
        GuildTag::factory()->create(['id' => 11, 'name' => 'Tag B']);
        GuildTag::factory()->create(['id' => 12, 'name' => 'Tag C']);

        $guildDataWithNoTags = $this->sampleGuildData();
        $guildDataWithNoTags['tags'] = [];
        $this->fakeSuccessfulGuildResponse($guildDataWithNoTags);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = $this->getService();
        $tags = $service->getGuildTags();

        $this->assertCount(3, $tags);
    }
}
