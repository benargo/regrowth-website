<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Concerns;

use App\Http\Integrations\WarcraftLogs\Exceptions\ApiException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures\ProbeRequest;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class HasCachingTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_caches_a_successful_post(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['data' => ['probe' => true]]));
        $connector = $this->makeConnector();

        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));
        $second = $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));

        $this->assertTrue($second->isCached());
        $mockClient->assertSentCount(1, ProbeRequest::class);
    }

    #[Test]
    public function different_variables_are_separate_cache_entries(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['data' => ['probe' => true]]));
        $connector = $this->makeConnector();

        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 2]));
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));

        $mockClient->assertSentCount(2, ProbeRequest::class);
    }

    #[Test]
    public function different_namespaces_are_separate_cache_entries(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['data' => ['probe' => true]]));
        $connector = $this->makeConnector();

        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 1]));
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Era, ['page' => 1]));

        $mockClient->assertSentCount(2, ProbeRequest::class);
    }

    #[Test]
    public function flushing_the_api_response_tag_clears_cached_responses(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['data' => ['probe' => true]]));
        $connector = $this->makeConnector();

        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));
        Cache::tags(['warcraftlogs-api-response'])->flush();
        $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary));

        $mockClient->assertSentCount(2, ProbeRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_cache_a_server_error(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['message' => 'boom'], 500));
        $connector = $this->makeConnector();

        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)), ApiException::class);
        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)), ApiException::class);

        $mockClient->assertSentCount(2, ProbeRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_cache_graphql_errors(): void
    {
        $mockClient = $this->fakeProbe(MockResponse::make(['errors' => [['message' => 'Bad query']]], 200, ['Content-Type' => 'application/json']));
        $connector = $this->makeConnector();

        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)), GraphQLException::class);
        $this->assertThrows(fn () => $connector->send(new ProbeRequest(WarcraftLogsNamespace::Anniversary)), GraphQLException::class);

        $mockClient->assertSentCount(2, ProbeRequest::class);
    }

    private function fakeProbe(MockResponse $probeResponse): MockClient
    {
        return Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => $probeResponse,
        ]);
    }
}
