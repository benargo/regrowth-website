<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\Exceptions\ApiException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures\ProbeRequest;

#[Group('warcraftlogs-integration')]
class WarcraftLogsConnectorTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_posts_to_the_namespace_host_with_a_bearer_token(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);

        $response = $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic));

        $pendingRequest = $response->getPendingRequest();
        $this->assertSame('https://classic.warcraftlogs.com/api/v2/client', $pendingRequest->getUrl());
        $this->assertSame(Method::POST, $pendingRequest->getMethod());
        $this->assertSame('Bearer test_token', $pendingRequest->headers()->get('Authorization'));
    }

    #[Test]
    public function it_requests_the_token_from_the_www_host_for_every_namespace(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::SeasonOfDiscovery));

        Saloon::assertSent(function (Request $request, Response $response): bool {
            return $request instanceof GetClientCredentialsTokenBasicAuthRequest
                && $response->getPendingRequest()->getUrl() === 'https://www.warcraftlogs.com/oauth/token';
        });
    }

    #[Test]
    public function it_fetches_a_token_once_and_caches_it_under_its_own_tagged_key(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);

        $connector = $this->makeConnector();
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Retail));

        $mockClient->assertSentCount(1, GetClientCredentialsTokenBasicAuthRequest::class);

        $cached = Cache::tags(['warcraftlogs', 'api-auth'])->get('warcraftlogs:access_token');
        $this->assertIsArray($cached);
        $this->assertSame('test_token', $cached['token']);
        $this->assertFalse(Cache::has('warcraftlogs:client_token'), 'The legacy token key must stay untouched.');
    }

    // ==================== getRequestException ====================

    #[Test]
    #[Group('error-handling')]
    public function it_throws_the_warcraft_logs_graphql_exception_for_errors_at_http_200(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make([
                'data' => null,
                'errors' => [['message' => 'Guild does not exist.']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        try {
            $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
            $this->fail('Expected a GraphQLException.');
        } catch (GraphQLException $exception) {
            $this->assertInstanceOf(WarcraftLogsRequestException::class, $exception);
            $this->assertSame([['message' => 'Guild does not exist.']], $exception->getErrors());
            $this->assertSame([
                'host' => 'fresh.warcraftlogs.com',
                'status' => 200,
                'error' => 'Guild does not exist.',
            ], $exception->context());
        }
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_api_exception_for_a_server_error(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['message' => 'boom'], 500),
        ]);

        try {
            $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Retail));
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $this->assertInstanceOf(WarcraftLogsRequestException::class, $exception);
            $this->assertSame(['host' => 'www.warcraftlogs.com', 'status' => 500], $exception->context());
        }
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_api_exception_rather_than_a_json_error_for_an_html_body(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make('<html><body>502 Bad Gateway</body></html>', 502, ['Content-Type' => 'text/html']),
        ]);

        $this->expectException(ApiException::class);

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic));
    }

    #[Test]
    #[Group('contract')]
    public function guild_not_found_is_a_warcraft_logs_graphql_exception(): void
    {
        $this->assertTrue(is_subclass_of(GuildNotFoundException::class, GraphQLException::class));
        $this->assertTrue(is_subclass_of(GuildNotFoundException::class, WarcraftLogsRequestException::class));
    }

    // ==================== rate limits ====================

    #[Test]
    #[Group('error-handling')]
    public function it_throws_rate_limit_reached_on_a_429(): void
    {
        $this->fakeTooManyAttempts();

        $this->expectException(RateLimitReachedException::class);

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_blocks_further_requests_without_calling_the_api_during_the_cooldown(): void
    {
        $mockClient = $this->fakeTooManyAttempts();
        $connector = $this->makeConnector();

        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1])), RateLimitReachedException::class);
        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 2])), RateLimitReachedException::class);

        $mockClient->assertSentCount(1, ProbeRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function the_cooldown_lasts_until_the_cached_points_reset(): void
    {
        $this->resetCache()->put(new RateLimitData(limitPerHour: 3600, pointsSpentThisHour: 3600.0, pointsResetIn: 1200));
        $this->fakeTooManyAttempts();

        $this->assertEqualsWithDelta(1200, $this->cooldownSecondsAfterA429(), 2);
    }

    #[Test]
    #[Group('error-handling')]
    public function the_cooldown_falls_back_to_an_hour_without_a_cached_reset(): void
    {
        $this->fakeTooManyAttempts();

        $this->assertEqualsWithDelta(3600, $this->cooldownSecondsAfterA429(), 2);
    }

    #[Test]
    public function flushing_the_rate_limit_tag_lifts_the_cooldown(): void
    {
        $this->fakeTooManyAttempts();
        $connector = $this->makeConnector();
        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)), RateLimitReachedException::class);

        Cache::tags(['warcraftlogs-rate-limit'])->flush();
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);

        $this->assertSame(200, $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary))->status());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_never_blocks_requests_without_a_429_however_many_are_sent(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);
        $connector = $this->makeConnector();

        foreach (range(1, 3601) as $attempt) {
            $response = $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
        }

        $this->assertSame(200, $response->status());
    }

    // ==================== helpers ====================

    private function fakeTooManyAttempts(): MockClient
    {
        return Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['error' => 'Too Many Requests'], 429),
        ]);
    }

    private function cooldownSecondsAfterA429(): int
    {
        try {
            $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
        } catch (RateLimitReachedException $exception) {
            return $exception->getLimit()->getRemainingSeconds();
        }

        $this->fail('Expected a RateLimitReachedException.');
    }
}
