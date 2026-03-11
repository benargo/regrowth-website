<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordGuildServiceTest extends TestCase
{
    private DiscordGuildService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiscordGuildService(
            'test_bot_token',
            '829020506907869214'
        );
    }

    #[Test]
    public function it_fetches_guild_member_data(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/123456789012345678' => Http::response([
                'user' => [
                    'id' => '123456789012345678',
                    'username' => 'testuser',
                    'discriminator' => '0',
                ],
                'nick' => 'TestNickname',
                'avatar' => 'abc123',
                'banner' => 'def456',
                'roles' => ['829021769448816691', '1265247017215594496'],
            ], 200),
        ]);

        $memberData = $this->service->getGuildMember('123456789012345678');

        $this->assertSame('123456789012345678', $memberData['user']['id']);
        $this->assertSame('testuser', $memberData['user']['username']);
        $this->assertSame('TestNickname', $memberData['nick']);
        $this->assertSame('abc123', $memberData['avatar']);
        $this->assertContains('829021769448816691', $memberData['roles']);
        $this->assertContains('1265247017215594496', $memberData['roles']);
    }

    #[Test]
    public function it_handles_null_nickname(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/123456789012345678' => Http::response([
                'user' => [
                    'id' => '123456789012345678',
                    'username' => 'testuser',
                    'discriminator' => '0',
                ],
                'nick' => null,
                'avatar' => null,
                'banner' => null,
                'roles' => ['829022020301094922'],
            ], 200),
        ]);

        $memberData = $this->service->getGuildMember('123456789012345678');

        $this->assertSame('123456789012345678', $memberData['user']['id']);
        $this->assertNull($memberData['nick']);
        $this->assertNull($memberData['avatar']);
        $this->assertNull($memberData['banner']);
    }

    #[Test]
    public function it_throws_exception_when_user_not_in_guild(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/123456789012345678' => Http::response([
                'message' => 'Unknown Member',
                'code' => 10007,
            ], 404),
        ]);

        $this->expectException(UserNotInGuildException::class);

        $this->service->getGuildMember('123456789012345678');
    }

    #[Test]
    public function it_throws_runtime_exception_on_api_error(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/123456789012345678' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch guild member data');

        $this->service->getGuildMember('123456789012345678');
    }

    #[Test]
    public function it_sends_correct_authorization_header(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/*' => Http::response([
                'nick' => null,
                'avatar' => null,
                'banner' => null,
                'roles' => [],
            ], 200),
        ]);

        $this->service->getGuildMember('123456789012345678');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bot test_bot_token');
        });
    }

    #[Test]
    public function it_returns_empty_roles_array_when_user_has_no_roles(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/123456789012345678' => Http::response([
                'user' => [
                    'id' => '123456789012345678',
                    'username' => 'TestUser',
                ],
                'nick' => 'TestUser',
                'avatar' => null,
                'banner' => null,
                'roles' => [],
            ], 200),
        ]);

        $memberData = $this->service->getGuildMember('123456789012345678');

        $this->assertSame('123456789012345678', $memberData['user']['id']);
        $this->assertIsArray($memberData['roles']);
        $this->assertEmpty($memberData['roles']);
    }

    #[Test]
    public function it_lists_guild_members_returning_a_cursor_paginator(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([
                ['user' => ['id' => '100000000000000001'], 'nick' => null, 'avatar' => null, 'roles' => [], 'joined_at' => '2021-01-01T00:00:00.000000+00:00', 'deaf' => false, 'mute' => false],
                ['user' => ['id' => '100000000000000002'], 'nick' => null, 'avatar' => null, 'roles' => [], 'joined_at' => '2021-01-02T00:00:00.000000+00:00', 'deaf' => false, 'mute' => false],
            ], 200),
        ]);

        $result = $this->service->listGuildMembers(perPage: 10);

        $this->assertInstanceOf(CursorPaginator::class, $result);
        $this->assertCount(2, $result->items());
    }

    #[Test]
    public function it_requests_one_extra_item_to_detect_next_page(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([], 200),
        ]);

        $this->service->listGuildMembers(perPage: 50);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return $params['limit'] === '51';
        });
    }

    #[Test]
    public function it_does_not_send_after_param_on_first_page(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([], 200),
        ]);

        $this->service->listGuildMembers();

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return ! array_key_exists('after', $params);
        });
    }

    #[Test]
    public function it_passes_after_param_from_cursor(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([], 200),
        ]);

        $cursor = new Cursor(['id' => '999999999999999999'], true);

        $this->service->listGuildMembers(cursor: $cursor);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return $params['after'] === '999999999999999999';
        });
    }

    #[Test]
    public function it_has_next_cursor_when_page_is_full(): void
    {
        $members = array_map(
            fn ($i) => ['user' => ['id' => (string) $i], 'nick' => null, 'avatar' => null, 'roles' => [], 'joined_at' => '2021-01-01T00:00:00.000000+00:00', 'deaf' => false, 'mute' => false],
            range(1, 3), // perPage=2, fetch 3 (N+1) → has more pages
        );

        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response($members, 200),
        ]);

        $result = $this->service->listGuildMembers(perPage: 2);

        $this->assertTrue($result->hasMorePages());
        $this->assertNotNull($result->nextCursor());
        $this->assertSame('2', $result->nextCursor()->parameter('id'));
    }

    #[Test]
    public function it_has_no_next_cursor_on_last_page(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([
                ['user' => ['id' => '100000000000000001'], 'nick' => null, 'avatar' => null, 'roles' => [], 'joined_at' => '2021-01-01T00:00:00.000000+00:00', 'deaf' => false, 'mute' => false],
            ], 200),
        ]);

        $result = $this->service->listGuildMembers(perPage: 100);

        $this->assertFalse($result->hasMorePages());
        $this->assertNull($result->nextCursor());
    }

    #[Test]
    public function it_caps_discord_limit_at_1000(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([], 200),
        ]);

        $this->service->listGuildMembers(perPage: 1000);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

            return $params['limit'] === '1000';
        });
    }

    #[Test]
    public function it_throws_runtime_exception_when_listing_members_fails(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members*' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to list guild members');

        $this->service->listGuildMembers();
    }

    #[Test]
    public function it_searches_guild_members_by_query(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/search*' => Http::response([
                [
                    'user' => [
                        'id' => '123456789012345678',
                        'username' => 'testuser',
                    ],
                    'nick' => 'TestNickname',
                    'avatar' => null,
                    'roles' => ['829021769448816691'],
                    'joined_at' => '2021-04-01T00:00:00.000000+00:00',
                    'deaf' => false,
                    'mute' => false,
                ],
            ], 200),
        ]);

        $members = $this->service->searchGuildMembers('test', 10);

        $this->assertCount(1, $members);
        $this->assertSame('testuser', $members[0]['user']['username']);
        $this->assertSame('TestNickname', $members[0]['nick']);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);

            return $queryParams['query'] === 'test' && $queryParams['limit'] === '10';
        });
    }

    #[Test]
    public function it_clamps_search_limit_to_valid_range(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/search*' => Http::response([], 200),
        ]);

        $this->service->searchGuildMembers('test', 0);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);

            return $queryParams['limit'] === '1';
        });

        $this->service->searchGuildMembers('test', 2000);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $queryParams);

            return $queryParams['limit'] === '1000';
        });
    }

    #[Test]
    public function it_throws_runtime_exception_on_search_api_error(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/members/search*' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to search guild members');

        $this->service->searchGuildMembers('test');
    }
}
