<?php

namespace Tests\Unit\Http\Integrations\Discord;

use App\Http\Integrations\Discord\Concerns\HasCaching;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Exceptions\NotFoundException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Throwable;

#[Group('discord-integration')]
class DiscordConnectorTest extends DiscordTestCase
{
    #[Test]
    public function it_targets_the_discord_v10_api(): void
    {
        $this->assertSame('https://discord.com/api/v10', $this->makeConnector()->resolveBaseUrl());
    }

    #[Test]
    public function it_authenticates_with_the_bot_token(): void
    {
        $response = $this->sendProbe(MockResponse::make([], 200));

        $this->assertSame('Bot test-token', $response->getPendingRequest()->headers()->get('Authorization'));
    }

    #[Test]
    public function it_sends_the_configured_user_agent(): void
    {
        $response = $this->sendProbe(MockResponse::make([], 200));

        $this->assertSame('DiscordBot (https://regrowth.test, 1.0)', $response->getPendingRequest()->headers()->get('User-Agent'));
    }

    #[Test]
    public function it_accepts_json(): void
    {
        $response = $this->sendProbe(MockResponse::make([], 200));

        $this->assertSame('application/json', $response->getPendingRequest()->headers()->get('Accept'));
    }

    #[Test]
    public function it_applies_short_connect_and_request_timeouts(): void
    {
        $response = $this->sendProbe(MockResponse::make([], 200));

        $this->assertEquals(3, $response->getPendingRequest()->config()->get('connect_timeout'));
        $this->assertEquals(10, $response->getPendingRequest()->config()->get('timeout'));
    }

    // ==================== getRequestException — rate limits ====================

    #[Test]
    #[Group('error-handling')]
    public function it_throws_rate_limited_exception_using_the_body_retry_after(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(
            ['message' => 'You are being rate limited.', 'retry_after' => 64.57, 'global' => true],
            429,
            ['Retry-After' => '65', 'X-RateLimit-Scope' => 'shared'],
        ));

        $this->assertInstanceOf(RateLimitedException::class, $exception);
        $this->assertSame(64.57, $exception->retryAfter);
        $this->assertSame(65, $exception->releaseAfter());
        $this->assertSame('shared', $exception->scope);
        $this->assertTrue($exception->global);
        $this->assertSame('probe', $exception->endpoint);
        $this->assertSame('GET', $exception->method);
        $this->assertSame(429, $exception->response?->status());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_falls_back_to_the_retry_after_header(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['message' => 'slow down'], 429, ['Retry-After' => '65']));

        $this->assertInstanceOf(RateLimitedException::class, $exception);
        $this->assertSame(65.0, $exception->retryAfter);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_floors_retry_after_at_one_second(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['retry_after' => 0], 429));

        $this->assertInstanceOf(RateLimitedException::class, $exception);
        $this->assertSame(1.0, $exception->retryAfter);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_defaults_retry_after_and_scope_when_discord_sends_neither(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make('', 429));

        $this->assertInstanceOf(RateLimitedException::class, $exception);
        $this->assertSame(1.0, $exception->retryAfter);
        $this->assertSame('user', $exception->scope);
        $this->assertFalse($exception->global);
    }

    // ==================== rate-limit gate ====================

    #[Test]
    #[Group('error-handling')]
    public function it_blocks_the_route_without_sending_until_the_retry_after_passes(): void
    {
        Saloon::fake([
            MockResponse::make(['message' => 'You are being rate limited.', 'retry_after' => 30, 'global' => false], 429),
            MockResponse::make([], 200),
        ]);
        $connector = $this->makeConnector();

        $this->sendExpectingException($connector, new DiscordProbeRequest);
        $blocked = $this->sendExpectingException($connector, new DiscordProbeRequest);

        $this->assertInstanceOf(RateLimitedException::class, $blocked);
        $this->assertNull($blocked->response);
        $this->assertGreaterThan(29.0, $blocked->retryAfter);
        Saloon::assertSentCount(1);

        $this->travel(31)->seconds();

        $this->assertSame(200, $connector->send(new DiscordProbeRequest)->status());
        Saloon::assertSentCount(2);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_blocks_every_route_after_a_global_rate_limit(): void
    {
        Saloon::fake([MockResponse::make(['message' => 'You are being rate limited.', 'retry_after' => 5, 'global' => true], 429)]);
        $connector = $this->makeConnector();

        $this->sendExpectingException($connector, new DiscordProbeRequest('/channels/111'));
        $blocked = $this->sendExpectingException($connector, new DiscordProbeRequest('/guilds/222/roles'));

        $this->assertInstanceOf(RateLimitedException::class, $blocked);
        $this->assertTrue($blocked->global);
        $this->assertSame('global', $blocked->scope);
        Saloon::assertSentCount(1);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_blocks_every_route_when_only_the_scope_header_reports_a_global_limit(): void
    {
        Saloon::fake([MockResponse::make(
            ['message' => 'You are being rate limited.', 'retry_after' => 5, 'global' => false],
            429,
            ['X-RateLimit-Scope' => 'global'],
        )]);
        $connector = $this->makeConnector();

        $limited = $this->sendExpectingException($connector, new DiscordProbeRequest('/channels/111'));
        $blocked = $this->sendExpectingException($connector, new DiscordProbeRequest('/guilds/222/roles'));

        $this->assertInstanceOf(RateLimitedException::class, $limited);
        $this->assertTrue($limited->global);
        $this->assertInstanceOf(RateLimitedException::class, $blocked);
        $this->assertTrue($blocked->global);
        Saloon::assertSentCount(1);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keys_routes_on_the_top_level_resource_and_groups_minor_ids(): void
    {
        Saloon::fake([
            MockResponse::make(['message' => 'You are being rate limited.', 'retry_after' => 5, 'global' => false], 429),
            MockResponse::make([], 200),
        ]);
        $connector = $this->makeConnector();

        $this->sendExpectingException($connector, new DiscordProbeRequest('/channels/111/messages/222'));
        $sameRoute = $this->sendExpectingException($connector, new DiscordProbeRequest('/channels/111/messages/333'));

        $this->assertInstanceOf(RateLimitedException::class, $sameRoute);
        $this->assertSame(200, $connector->send(new DiscordProbeRequest('/channels/999/messages/222'))->status());
        Saloon::assertSentCount(2);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_waits_for_an_exhausted_bucket_to_reset_before_sending_again(): void
    {
        Saloon::fake([MockResponse::make([], 200, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '1.5'])]);
        $connector = $this->makeConnector();

        $this->assertSame(200, $connector->send(new DiscordProbeRequest)->status());
        $blocked = $this->sendExpectingException($connector, new DiscordProbeRequest);

        $this->assertInstanceOf(RateLimitedException::class, $blocked);
        $this->assertLessThanOrEqual(1.5, $blocked->retryAfter);
        Saloon::assertSentCount(1);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_ignores_the_rate_limit_headers_of_a_cached_response(): void
    {
        $this->freezeTime();
        Saloon::fake([
            MockResponse::make([], 200, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset-After' => '1']),
            MockResponse::make([], 200),
        ]);
        $connector = $this->makeConnector();

        $connector->send(new CacheableDiscordProbeRequest);
        $this->travel(2)->seconds();

        $this->assertTrue($connector->send(new CacheableDiscordProbeRequest)->isCached());
        $this->assertSame(200, $connector->send(new DiscordProbeRequest)->status());
        Saloon::assertSentCount(2);
    }

    // ==================== getRequestException — not found ====================

    #[Test]
    #[Group('error-handling')]
    public function it_translates_unknown_message_to_message_not_found(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['message' => 'Unknown Message', 'code' => 10008], 404));

        $this->assertInstanceOf(MessageNotFoundException::class, $exception);
        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertSame(10008, $exception->getDiscordCode());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_translates_unknown_member_to_user_not_in_guild(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['message' => 'Unknown Member', 'code' => 10007], 404));

        $this->assertInstanceOf(UserNotInGuildException::class, $exception);
        $this->assertInstanceOf(NotFoundException::class, $exception);
    }

    #[Test]
    #[Group('error-handling')]
    #[TestWith([10003, 'Unknown Channel'])]
    #[TestWith([10004, 'Unknown Guild'])]
    #[TestWith([10013, 'Unknown User'])]
    public function it_translates_other_not_found_codes_to_api_exception(int $code, string $message): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['message' => $message, 'code' => $code], 404));

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertNotInstanceOf(NotFoundException::class, $exception);
        $this->assertSame($code, $exception->getDiscordCode());
    }

    // ==================== getRequestException — other errors ====================

    #[Test]
    #[Group('error-handling')]
    public function it_translates_a_server_error_to_api_exception_with_request_details(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make(['message' => '500: Internal Server Error', 'code' => 0], 500));

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('GET', $exception->getMethod());
        $this->assertSame('probe', $exception->getEndpoint());
        $this->assertSame(500, $exception->getDiscordStatus());
        $this->assertSame(0, $exception->getDiscordCode());
        $this->assertSame(['message' => '500: Internal Server Error', 'code' => 0], $exception->getDiscordBody());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_translates_a_non_json_error_body_to_an_api_exception(): void
    {
        $exception = $this->sendProbeExpectingException(MockResponse::make('<html><body>Cloudflare: Access denied</body></html>', 403));

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertNull($exception->getDiscordBody());
        $this->assertNull($exception->getDiscordCode());
    }

    private function sendProbe(MockResponse $mockResponse): Response
    {
        Saloon::fake([DiscordProbeRequest::class => $mockResponse]);

        return $this->makeConnector()->send(new DiscordProbeRequest);
    }

    private function sendProbeExpectingException(MockResponse $mockResponse): Throwable
    {
        try {
            $this->sendProbe($mockResponse);
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('Expected the Discord connector to throw.');
    }

    private function sendExpectingException(DiscordConnector $connector, Request $request): Throwable
    {
        try {
            $connector->send($request);
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('Expected the Discord connector to throw.');
    }
}

/**
 * Minimal concrete request used only to exercise the connector in this test.
 */
class DiscordProbeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $endpoint = '/probe',
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }
}

/**
 * Minimal cacheable request used only to exercise the connector with cached responses.
 */
class CacheableDiscordProbeRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/probe';
    }

    public function cacheExpiryInSeconds(): int
    {
        return 3600;
    }
}
