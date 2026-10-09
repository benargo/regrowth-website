<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class ProbeRequestTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('contract')]
    public function it_exposes_the_namespace_it_was_built_with(): void
    {
        $request = new ProbeRequest(WarcraftLogsNamespace::Era);

        $this->assertSame(WarcraftLogsNamespace::Era, $request->namespace());
    }

    #[Test]
    #[Group('contract')]
    public function it_resolves_the_endpoint_from_the_namespace(): void
    {
        $request = new ProbeRequest(WarcraftLogsNamespace::SeasonOfDiscovery);

        $this->assertSame('https://sod.warcraftlogs.com/api/v2/client', $request->resolveEndpoint());
    }

    #[Test]
    #[Group('contract')]
    public function it_is_cacheable_for_five_minutes(): void
    {
        $request = new ProbeRequest(WarcraftLogsNamespace::Anniversary);

        $this->assertInstanceOf(Cacheable::class, $request);
        $this->assertSame(300, $request->cacheExpiryInSeconds());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_sends_the_probe_query_to_the_namespace_host(): void
    {
        $pendingRequest = $this->sendProbe(new ProbeRequest(WarcraftLogsNamespace::Anniversary))->getPendingRequest();

        $body = $pendingRequest->body()->all();

        $this->assertSame('https://fresh.warcraftlogs.com/api/v2/client', $pendingRequest->getUrl());
        $this->assertSame('query Probe($page: Int) { probe(page: $page) }', $body['query']);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_sends_the_given_variables(): void
    {
        $request = new ProbeRequest(WarcraftLogsNamespace::Anniversary, ['page' => 2]);

        $body = $this->sendProbe($request)->getPendingRequest()->body()->all();

        $this->assertSame(['page' => 2], $body['variables']);
    }

    #[Test]
    public function it_sends_empty_variables_by_default(): void
    {
        $request = new ProbeRequest(WarcraftLogsNamespace::Anniversary);

        $body = json_encode($this->sendProbe($request)->getPendingRequest()->body()->all(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"variables":{}', $body);
    }

    private function sendProbe(ProbeRequest $request): Response
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => MockResponse::make(['data' => ['probe' => true]]),
        ]);

        return $this->makeConnector()->send($request);
    }
}
