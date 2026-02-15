<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\GuildTags;
use App\Events\AddonSettingsProcessed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuildTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeSuccessfulTagsResponse(array $tagsData): void
    {
        Event::fake(AddonSettingsProcessed::class);
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => [
                    'guildData' => [
                        'guild' => [
                            'id' => 774848,
                            'tags' => $tagsData,
                        ],
                    ],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function sampleTagsData(): array
    {
        return [
            ['id' => 1, 'name' => 'Main Raider'],
            ['id' => 2, 'name' => 'Alt'],
            ['id' => 3, 'name' => 'Trial'],
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

    protected function makeGuildTags(array $configOverrides = [], ?array $tagsData = null): GuildTags
    {
        $this->fakeSuccessfulTagsResponse($tagsData ?? $this->sampleTagsData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $config = $this->makeConfig($configOverrides);
        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new GuildTags($config, $auth);
    }

    public function test_constructor_fetches_guild_tags_data(): void
    {
        $this->makeGuildTags();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['variables']['id']) && $body['variables']['id'] === 774848;
        });
    }

    public function test_constructor_uses_configured_guild_id(): void
    {
        $this->makeGuildTags(['guild_id' => 774848]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['variables']['id'] === 774848;
        });
    }

    public function test_constructor_creates_guild_tag_models(): void
    {
        $this->makeGuildTags();

        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 1, 'name' => 'Main Raider']);
        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 2, 'name' => 'Alt']);
        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 3, 'name' => 'Trial']);
    }

    public function test_constructor_updates_existing_guild_tag_models(): void
    {
        GuildTag::factory()->create(['id' => 1, 'name' => 'Old Name']);

        $this->makeGuildTags();

        $this->assertDatabaseHas('wcl_guild_tags', ['id' => 1, 'name' => 'Main Raider']);
        $this->assertDatabaseMissing('wcl_guild_tags', ['id' => 1, 'name' => 'Old Name']);
    }

    public function test_constructor_handles_empty_tags_response(): void
    {
        $guildTags = $this->makeGuildTags(tagsData: []);

        $this->assertEmpty($guildTags->toArray());
    }

    public function test_constructor_caches_graphql_response(): void
    {
        $this->fakeSuccessfulTagsResponse($this->sampleTagsData());

        Cache::shouldReceive('remember')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::type('int'), \Mockery::type('callable'))
            ->andReturn([
                'guildData' => [
                    'guild' => [
                        'id' => 774848,
                        'tags' => $this->sampleTagsData(),
                    ],
                ],
            ]);

        $config = $this->makeConfig();
        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        new GuildTags($config, $auth);
    }

    public function test_find_returns_guild_tag_by_id(): void
    {
        $guildTags = $this->makeGuildTags();

        $tag = $guildTags->find(1);

        $this->assertInstanceOf(GuildTag::class, $tag);
        $this->assertEquals(1, $tag->id);
        $this->assertEquals('Main Raider', $tag->name);
    }

    public function test_find_returns_null_for_non_existent_id(): void
    {
        $guildTags = $this->makeGuildTags();

        $tag = $guildTags->find(999);

        $this->assertNull($tag);
    }

    public function test_to_array_returns_all_tags(): void
    {
        $guildTags = $this->makeGuildTags();

        $array = $guildTags->toArray();

        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        $this->assertArrayHasKey(1, $array);
        $this->assertArrayHasKey(2, $array);
        $this->assertArrayHasKey(3, $array);
    }

    public function test_to_array_values_are_guild_tag_models(): void
    {
        $guildTags = $this->makeGuildTags();

        $array = $guildTags->toArray();

        foreach ($array as $tag) {
            $this->assertInstanceOf(GuildTag::class, $tag);
        }
    }

    public function test_to_collection_returns_collection_instance(): void
    {
        $guildTags = $this->makeGuildTags();

        $collection = $guildTags->toCollection();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $collection);
    }

    public function test_to_collection_contains_all_tags(): void
    {
        $guildTags = $this->makeGuildTags();

        $collection = $guildTags->toCollection();

        $this->assertCount(3, $collection);
        $this->assertTrue($collection->has(1));
        $this->assertTrue($collection->has(2));
        $this->assertTrue($collection->has(3));
    }

    public function test_to_collection_values_are_guild_tag_models(): void
    {
        $guildTags = $this->makeGuildTags();

        $collection = $guildTags->toCollection();

        $collection->each(function ($tag) {
            $this->assertInstanceOf(GuildTag::class, $tag);
        });
    }

    public function test_normalize_guild_tag_ids_returns_empty_array_for_null(): void
    {
        $result = GuildTags::normalizeGuildTagIDs(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_normalize_guild_tag_ids_returns_array_with_single_int(): void
    {
        $result = GuildTags::normalizeGuildTagIDs(42);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals([42], $result);
    }

    public function test_normalize_guild_tag_ids_returns_same_array_when_given_array(): void
    {
        $input = [1, 2, 3];

        $result = GuildTags::normalizeGuildTagIDs($input);

        $this->assertIsArray($result);
        $this->assertEquals($input, $result);
    }

    public function test_normalize_guild_tag_ids_returns_empty_array_when_given_empty_array(): void
    {
        $result = GuildTags::normalizeGuildTagIDs([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_guild_tags_service_can_be_resolved_from_container(): void
    {
        $this->fakeSuccessfulTagsResponse($this->sampleTagsData());

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $service = app(GuildTags::class);

        $this->assertInstanceOf(GuildTags::class, $service);
    }
}
