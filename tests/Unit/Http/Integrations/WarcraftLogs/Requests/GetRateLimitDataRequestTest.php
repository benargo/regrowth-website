<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\Requests\GetRateLimitDataRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class GetRateLimitDataRequestTest extends WarcraftLogsTestCase
{
    #[Test]
    public function it_queries_rate_limit_data_on_the_namespace_host(): void
    {
        $this->fakeRateLimitData();

        $response = $this->makeConnector()->send(new GetRateLimitDataRequest(WarcraftLogsNamespace::SeasonOfDiscovery));

        $pendingRequest = $response->getPendingRequest();
        $this->assertSame('https://sod.warcraftlogs.com/api/v2/client', $pendingRequest->getUrl());

        $body = json_encode($pendingRequest->body()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('rateLimitData', $body);
        $this->assertStringContainsString('limitPerHour', $body);
        $this->assertStringContainsString('pointsSpentThisHour', $body);
        $this->assertStringContainsString('pointsResetIn', $body);
        $this->assertStringContainsString('"variables":{}', $body);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_maps_the_response_to_rate_limit_data(): void
    {
        $this->fakeRateLimitData();

        $rateLimit = $this->makeConnector()->send(new GetRateLimitDataRequest(WarcraftLogsNamespace::Anniversary))->dto();

        $this->assertInstanceOf(RateLimitData::class, $rateLimit);
        $this->assertSame(3600, $rateLimit->limitPerHour);
        $this->assertSame(2400.5, $rateLimit->pointsSpentThisHour);
        $this->assertSame(1260, $rateLimit->pointsResetIn);
    }

    #[Test]
    public function it_is_never_cached(): void
    {
        $mockClient = $this->fakeRateLimitData();
        $connector = $this->makeConnector();

        $connector->send(new GetRateLimitDataRequest(WarcraftLogsNamespace::Anniversary));
        $connector->send(new GetRateLimitDataRequest(WarcraftLogsNamespace::Anniversary));

        $mockClient->assertSentCount(2, GetRateLimitDataRequest::class);
    }

    private function fakeRateLimitData(): MockClient
    {
        return Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetRateLimitDataRequest::class => MockResponse::make($this->samplePayload()),
        ]);
    }

    /**
     * @return array{data: array{rateLimitData: array{limitPerHour: int, pointsSpentThisHour: float, pointsResetIn: int}}}
     */
    private function samplePayload(): array
    {
        return [
            'data' => [
                'rateLimitData' => [
                    'limitPerHour' => 3600,
                    'pointsSpentThisHour' => 2400.5,
                    'pointsResetIn' => 1260,
                ],
            ],
        ];
    }
}
