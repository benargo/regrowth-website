<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\Data\Reports\ReportData;
use App\Http\Integrations\WarcraftLogs\Exceptions\ApiException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\Requests\GetReportsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
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

    // ==================== revoked tokens ====================

    #[Test]
    #[Group('error-handling')]
    public function it_discards_a_revoked_cached_token_and_retries_once_with_a_fresh_one(): void
    {
        $this->cacheToken('revoked_token');
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => fn (PendingRequest $pendingRequest): MockResponse => $pendingRequest->headers()->get('Authorization') === 'Bearer revoked_token'
                ? MockResponse::make(['error' => 'Unauthenticated.'], 401)
                : MockResponse::make(['data' => ['probe' => true]]),
        ]);

        $response = $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic));

        $this->assertSame(200, $response->status());
        $this->assertSame('Bearer test_token', $response->getPendingRequest()->headers()->get('Authorization'));
        $mockClient->assertSentCount(1, GetClientCredentialsTokenBasicAuthRequest::class);
        $mockClient->assertSentCount(2, ProbeRequest::class);
        $this->assertSame('test_token', Cache::tags(['warcraftlogs', 'api-auth'])->get('warcraftlogs:access_token')['token']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rethrows_a_second_401_as_a_credential_failure_without_looping(): void
    {
        $this->cacheToken('revoked_token');
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['error' => 'Unauthenticated.'], 401),
        ]);

        try {
            $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic));
            $this->fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            $this->assertSame(401, $exception->getStatus());
        }

        $mockClient->assertSentCount(2, ProbeRequest::class);
        $mockClient->assertSentCount(1, GetClientCredentialsTokenBasicAuthRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_retry_or_discard_the_token_for_other_failures(): void
    {
        $this->cacheToken('valid_token');
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['message' => 'boom'], 500),
        ]);

        $this->assertThrows(fn () => $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic)), ApiException::class);

        $mockClient->assertSentCount(1, ProbeRequest::class);
        $mockClient->assertNotSent(GetClientCredentialsTokenBasicAuthRequest::class);
        $this->assertSame('valid_token', Cache::tags(['warcraftlogs', 'api-auth'])->get('warcraftlogs:access_token')['token']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_retry_a_401_from_the_token_endpoint(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => MockResponse::make(['error' => 'invalid_client'], 401),
        ]);

        $this->assertThrows(fn () => $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic)), ApiException::class);

        $mockClient->assertSentCount(1, GetClientCredentialsTokenBasicAuthRequest::class);
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

    // ==================== paginate ====================

    #[Test]
    #[Group('happy-path')]
    public function it_paginates_through_the_dtos_of_every_page_in_order(): void
    {
        $mockClient = $this->fakeTwoReportPages();

        $codes = $this->makeConnector()
            ->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))
            ->collect()
            ->map(fn (ReportData $report): string => $report->code)
            ->values()
            ->all();

        $this->assertSame(['aaa', 'bbb', 'ccc'], $codes);
        $mockClient->assertSentCount(2, GetReportsRequest::class);
    }

    #[Test]
    public function it_stops_paginating_after_one_page_when_there_are_no_more_pages(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => MockResponse::make($this->reportsPage(['aaa'], hasMorePages: false)),
        ]);

        iterator_to_array($this->makeConnector()->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))->items(), false);

        $mockClient->assertSentCount(1, GetReportsRequest::class);
    }

    #[Test]
    public function it_treats_a_missing_has_more_pages_flag_as_the_last_page(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => MockResponse::make($this->reportsPage(['aaa'], hasMorePages: null)),
        ]);

        iterator_to_array($this->makeConnector()->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))->items(), false);

        $mockClient->assertSentCount(1, GetReportsRequest::class);
    }

    #[Test]
    public function it_sends_one_based_page_numbers_and_keeps_the_other_variables(): void
    {
        $this->fakeTwoReportPages();

        $variables = [];
        foreach ($this->makeConnector()->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic)) as $response) {
            $variables[] = $response->getPendingRequest()->body()->all()['variables'];
        }

        $this->assertSame([
            ['guildTagID' => 1234, 'page' => 1, 'limit' => 100],
            ['guildTagID' => 1234, 'page' => 2, 'limit' => 100],
        ], $variables);
    }

    #[Test]
    public function it_sends_the_right_page_even_when_the_body_was_read_first(): void
    {
        $this->fakeTwoReportPages();
        $request = new GetReportsRequest(1234, WarcraftLogsNamespace::Classic);
        $request->body()->all();

        $pages = [];
        foreach ($this->makeConnector()->paginate($request) as $response) {
            $pages[] = $response->getPendingRequest()->body()->all()['variables']['page'];
        }

        $this->assertSame([1, 2], $pages);
    }

    // ==================== helpers ====================

    private function cacheToken(string $token): void
    {
        Cache::tags(['warcraftlogs', 'api-auth'])->put(
            'warcraftlogs:access_token',
            ['token' => $token, 'expires_at' => now()->addYear()->getTimestamp()],
            now()->addYear(),
        );
    }

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

    private function fakeTwoReportPages(): MockClient
    {
        return Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => fn (PendingRequest $pendingRequest): MockResponse => $pendingRequest->body()->all()['variables']['page'] === 1
                ? MockResponse::make($this->reportsPage(['aaa', 'bbb'], hasMorePages: true))
                : MockResponse::make($this->reportsPage(['ccc'], hasMorePages: false)),
        ]);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, mixed>
     */
    private function reportsPage(array $codes, ?bool $hasMorePages): array
    {
        $reports = [
            'data' => array_map(fn (string $code): array => [
                'code' => $code,
                'title' => "Report {$code}",
                'startTime' => 1700000000123.0,
                'endTime' => 1700003600456.0,
                'guildTag' => ['id' => 1234, 'name' => 'Main Raid'],
                'zone' => null,
            ], $codes),
        ];

        if ($hasMorePages !== null) {
            $reports['has_more_pages'] = $hasMorePages;
        }

        return ['data' => ['reportData' => ['reports' => $reports]]];
    }
}
