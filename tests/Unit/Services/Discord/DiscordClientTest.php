<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordClient;
use App\Services\Discord\Exceptions\DiscordRequestException;
use App\Services\Discord\Exceptions\MessageNotFoundException;
use App\Services\Discord\Exceptions\RateLimitedException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class DiscordClientTest extends TestCase
{
    private DiscordClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->client = new DiscordClient('test-bot-token', 'DiscordBot (https://regrowth.gg, 1.0)');
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_get_requests(): void
    {
        $this->client->get('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_get_requests_to_the_correct_url(): void
    {
        $this->client->get('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://discord.com/api/v10/channels/123';
        });
    }

    #[Test]
    public function it_sends_query_parameters_with_get_requests(): void
    {
        $this->client->get('/guilds/123/members', ['limit' => 100, 'after' => '456']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'limit=100')
                && str_contains($request->url(), 'after=456');
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_post_requests(): void
    {
        $this->client->post('/channels/123/messages', ['content' => 'Hello']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_post_requests_to_the_correct_url_with_body(): void
    {
        $this->client->post('/channels/123/messages', ['content' => 'Hello']);

        Http::assertSent(function (Request $request) {
            return $request->method('POST')
                && $request->url() === 'https://discord.com/api/v10/channels/123/messages'
                && $request->data()['content'] === 'Hello';
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_patch_requests(): void
    {
        $this->client->patch('/channels/123', ['name' => 'new-name']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_patch_requests_to_the_correct_url_with_body(): void
    {
        $this->client->patch('/channels/123', ['name' => 'new-name']);

        Http::assertSent(function (Request $request) {
            return $request->method('PATCH')
                && $request->url() === 'https://discord.com/api/v10/channels/123'
                && $request->data()['name'] === 'new-name';
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_delete_requests(): void
    {
        $this->client->delete('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_delete_requests_to_the_correct_url(): void
    {
        $this->client->delete('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->method('DELETE')
                && $request->url() === 'https://discord.com/api/v10/channels/123';
        });
    }

    #[Test]
    public function it_sends_the_discord_bot_user_agent_on_every_request(): void
    {
        $this->client->get('/channels/123');

        Http::assertSent(function (Request $request) {
            $ua = $request->header('User-Agent')[0] ?? '';

            return str_starts_with($ua, 'DiscordBot (https://regrowth.gg');
        });
    }

    #[Test]
    public function it_throws_rate_limited_exception_on_429_without_retrying(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response(
                ['retry_after' => 2.5, 'message' => 'You are being rate limited.'],
                429,
                ['X-RateLimit-Scope' => 'user'],
            ),
        ]);

        try {
            $this->client->get('/channels/123');
            $this->fail('Expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame('user', $e->scope);
            $this->assertSame('channels/123', $e->endpoint);
            $this->assertSame(2.5, $e->retryAfter);
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_falls_back_to_retry_after_header_when_body_has_no_retry_after(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response(
                ['message' => 'You are being rate limited.'],
                429,
                ['Retry-After' => '5', 'X-RateLimit-Scope' => 'shared'],
            ),
        ]);

        try {
            $this->client->get('/channels/123');
            $this->fail('Expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(5.0, $e->retryAfter);
            $this->assertSame('shared', $e->scope);
        }
    }

    #[Test]
    public function it_replaces_zero_retry_after_header_with_one_second_floor(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response([], 429, ['Retry-After' => '0']),
        ]);

        try {
            $this->client->get('test');
            $this->fail('Expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(1.0, $e->retryAfter);
        }
    }

    #[Test]
    public function get_throws_discord_request_exception_on_non_2xx_status(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response(['code' => 50001, 'message' => 'Missing Access'], 403),
        ]);

        try {
            $this->client->get('/channels/123');
            $this->fail('Expected DiscordRequestException');
        } catch (DiscordRequestException $e) {
            $this->assertSame('GET', $e->method);
            $this->assertSame('channels/123', $e->endpoint);
            $this->assertSame(403, $e->status);
            $this->assertSame(50001, $e->discordCode);
            $this->assertStringNotContainsString('Missing Access', $e->getMessage());
        }
    }

    #[Group('error-handling')]
    #[Test]
    public function get_throws_message_not_found_exception_on_404_for_message_endpoint(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response(['code' => 10008, 'message' => 'Unknown Message'], 404),
        ]);

        $this->expectException(MessageNotFoundException::class);
        $this->expectExceptionMessage('Message not found: GET channels/123/messages/456');

        $this->client->get('/channels/123/messages/456');
    }

    #[Group('error-handling')]
    #[Test]
    public function delete_throws_discord_request_exception_on_500(): void
    {
        Http::swap(new Factory);
        Http::fake([
            'discord.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(DiscordRequestException::class);

        $this->client->delete('/channels/123/messages/456');
    }
}
