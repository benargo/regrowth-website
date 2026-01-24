<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\Exceptions\UserNotInGuildException;
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
                'nick' => 'TestUser',
                'avatar' => null,
                'banner' => null,
                'roles' => [],
            ], 200),
        ]);

        $memberData = $this->service->getGuildMember('123456789012345678');

        $this->assertIsArray($memberData['roles']);
        $this->assertEmpty($memberData['roles']);
    }
}
