<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Middleware;

use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\Requests\GetRateLimitDataRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures\ProbeRequest;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class MonitorRateLimitTest extends WarcraftLogsTestCase
{
    #[Test]
    public function it_warns_when_points_are_at_or_below_ten_percent(): void
    {
        Log::spy();
        $this->fakeProbe($this->okResponse(remaining: 360));

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Warcraft Logs API points are running low: 360 of 3600 remaining.'
                && $context === ['host' => 'fresh.warcraftlogs.com', 'limit' => 3600, 'remaining' => 360])
            ->once();
    }

    #[Test]
    #[Group('happy-path')]
    public function it_does_nothing_while_points_are_healthy(): void
    {
        Log::spy();
        $this->fakeProbe($this->okResponse(remaining: 3000));

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        Log::shouldNotHaveReceived('warning');
        Saloon::assertNotSent(GetRateLimitDataRequest::class);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_records_the_reset_time_when_points_drop_below_half(): void
    {
        $this->freezeTime();
        $mockClient = $this->fakeProbe($this->okResponse(remaining: 1000));

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Classic));

        $mockClient->assertSentCount(1, GetRateLimitDataRequest::class);
        Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof GetRateLimitDataRequest
            && $response->getPendingRequest()->getUrl() === 'https://classic.warcraftlogs.com/api/v2/client');
        $this->assertSame(now()->addSeconds(1260)->getTimestamp(), $this->resetCache()->resetsAt()?->getTimestamp());

        $this->travel(1260)->seconds();

        $this->assertNull($this->resetCache()->resetsAt(), 'The reset time expires with the window.');
    }

    #[Test]
    public function it_sends_at_most_one_lookup_per_window(): void
    {
        $mockClient = $this->fakeProbe($this->okResponse(remaining: 1000));
        $connector = $this->makeConnector();

        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 2]));

        $mockClient->assertSentCount(1, GetRateLimitDataRequest::class);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_sends_no_lookup_at_exactly_half(): void
    {
        $this->fakeProbe($this->okResponse(remaining: 1800));

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        Saloon::assertNotSent(GetRateLimitDataRequest::class);
    }

    #[Test]
    public function it_ignores_a_cached_response(): void
    {
        $mockClient = $this->fakeProbe($this->okResponse(remaining: 1000));
        $connector = $this->makeConnector();
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
        Cache::tags(['warcraftlogs-rate-limit'])->flush();

        $cached = $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        $this->assertTrue($cached->isCached());
        $mockClient->assertSentCount(1, GetRateLimitDataRequest::class);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Test]
    #[Group('edge-case')]
    #[DataProvider('unusableHeaders')]
    public function it_ignores_unusable_rate_limit_headers(array $headers): void
    {
        Log::spy();
        $this->fakeProbe(MockResponse::make(['data' => ['probe' => true]], 200, $headers));

        $response = $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        $this->assertSame(200, $response->status());
        Saloon::assertNotSent(GetRateLimitDataRequest::class);
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function it_does_not_recurse_on_the_lookup_response(): void
    {
        $mockClient = $this->fakeProbe(
            $this->okResponse(remaining: 1000),
            lookupResponse: MockResponse::make($this->rateLimitPayload(), 200, $this->headers(remaining: 900)),
        );

        $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        $mockClient->assertSentCount(1, GetRateLimitDataRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    #[DataProvider('failedLookups')]
    public function it_logs_and_swallows_a_failed_lookup(MockResponse $lookupResponse): void
    {
        Log::spy();
        $this->fakeProbe($this->okResponse(remaining: 1000), lookupResponse: $lookupResponse);

        $response = $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        $this->assertSame(200, $response->status());
        $this->assertNull($this->resetCache()->resetsAt());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_starts_with($message, 'Could not fetch Warcraft Logs rate limit data:'))
            ->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_records_the_reset_time_and_still_throws_for_a_graphql_error(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(
            ['errors' => [['message' => 'Bad query']]],
            200,
            [...$this->headers(remaining: 1000), 'Content-Type' => 'application/json'],
        ));

        $this->assertThrows(
            fn () => $this->makeConnector()->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)),
            GraphQLException::class,
        );

        $mockClient->assertSentCount(1, GetRateLimitDataRequest::class);
        $this->assertNotNull($this->resetCache()->resetsAt());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function unusableHeaders(): array
    {
        return [
            'no headers' => [[]],
            'limit only' => [['x-ratelimit-limit' => '3600']],
            'remaining only' => [['x-ratelimit-remaining' => '10']],
            'zero limit' => [['x-ratelimit-limit' => '0', 'x-ratelimit-remaining' => '0']],
            'non-numeric' => [['x-ratelimit-limit' => 'lots', 'x-ratelimit-remaining' => 'few']],
        ];
    }

    /**
     * @return array<string, array{0: MockResponse}>
     */
    public static function failedLookups(): array
    {
        return [
            'server error' => [MockResponse::make(['message' => 'boom'], 500)],
            'graphql error' => [MockResponse::make(['errors' => [['message' => 'Unauthorized field']]], 200, ['Content-Type' => 'application/json'])],
            'non-json body' => [MockResponse::make('<html><body>Down for maintenance</body></html>', 200, ['Content-Type' => 'text/html'])],
            'missing rate limit data' => [MockResponse::make(['data' => []])],
            'partial rate limit data' => [MockResponse::make(['data' => ['rateLimitData' => ['limitPerHour' => 3600]]])],
        ];
    }

    private function fakeProbe(MockResponse $probeResponse, ?MockResponse $lookupResponse = null): MockClient
    {
        return Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => $probeResponse,
            GetRateLimitDataRequest::class => $lookupResponse ?? MockResponse::make($this->rateLimitPayload()),
        ]);
    }

    private function okResponse(int $remaining): MockResponse
    {
        return MockResponse::make(['data' => ['probe' => true]], 200, $this->headers($remaining));
    }

    /**
     * @return array<string, string>
     */
    private function headers(int $remaining): array
    {
        return ['x-ratelimit-limit' => '3600', 'x-ratelimit-remaining' => (string) $remaining];
    }

    /**
     * @return array{data: array{rateLimitData: array{limitPerHour: int, pointsSpentThisHour: float, pointsResetIn: int}}}
     */
    private function rateLimitPayload(): array
    {
        return ['data' => ['rateLimitData' => ['limitPerHour' => 3600, 'pointsSpentThisHour' => 2600.0, 'pointsResetIn' => 1260]]];
    }
}
