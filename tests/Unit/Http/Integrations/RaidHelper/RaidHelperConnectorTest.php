<?php

namespace Tests\Unit\Http\Integrations\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\Statuses\InternalServerErrorException;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\TestCase;

class RaidHelperConnectorTest extends TestCase
{
    #[Test]
    public function it_authenticates_with_the_bare_token_and_no_prefix(): void
    {
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make([], 200),
        ]);

        $this->makeConnector()->send(new ConnectorProbeRequest);

        // Auth is applied during the send pipeline, so the resolved header lives on the
        // sent PendingRequest (reachable via the recorded Response), not the base Request.
        Saloon::assertSent(function (Request $request, Response $response) {
            return $response->getPendingRequest()->headers()->get('Authorization') === 'test-token';
        });
    }

    #[Test]
    public function it_exposes_the_configured_server_id(): void
    {
        $this->assertSame('111222333444555666', $this->makeConnector()->serverId());
    }

    #[Test]
    public function it_throws_not_found_exception_on_a_404(): void
    {
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make(['reason' => 'unknown composition', 'status' => 'failed'], 404),
        ]);

        $this->expectException(NotFoundException::class);

        $this->makeConnector()->send(new ConnectorProbeRequest);
    }

    #[Test]
    public function it_throws_request_exception_on_a_generic_server_error(): void
    {
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make(['message' => 'boom'], 500),
        ]);

        $this->expectException(InternalServerErrorException::class);

        $this->makeConnector()->send(new ConnectorProbeRequest);
    }

    // ==================== Rate limits ====================

    #[Test]
    public function it_throws_rate_limit_reached_on_a_429_with_retry_after_header(): void
    {
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make([], 429, ['Retry-After' => '5']),
        ]);

        $this->expectException(RateLimitReachedException::class);

        $this->makeConnector()->send(new ConnectorProbeRequest);
    }

    #[Test]
    public function it_throws_rate_limit_reached_on_a_429_with_no_retry_after_header(): void
    {
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make([], 429),
        ]);

        $this->expectException(RateLimitReachedException::class);

        $this->makeConnector()->send(new ConnectorProbeRequest);
    }

    #[Test]
    public function its_default_store_does_not_leak_exceeded_state_between_instances(): void
    {
        // A 429 against one connector marks its limit exceeded. Because the default store
        // is cache-backed (not the process-global MemoryStore), a fresh connector built in
        // the next test gets a clean slate once the array cache is flushed. We simulate the
        // cross-test boundary here by flushing the cache between sends.
        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make([], 429, ['Retry-After' => '60']),
        ]);

        try {
            $this->makeConnector()->send(new ConnectorProbeRequest);
        } catch (RateLimitReachedException) {
            // Expected: the first connector hits the limit.
        }

        Cache::flush();

        Saloon::fake([
            ConnectorProbeRequest::class => MockResponse::make(['ok' => true], 200),
        ]);

        $response = $this->makeConnector()->send(new ConnectorProbeRequest);

        $this->assertSame(200, $response->status());
    }

    private function makeConnector(): RaidHelperConnector
    {
        return new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
    }
}

/**
 * Minimal concrete request used only to exercise the connector in this test.
 */
class ConnectorProbeRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/probe';
    }
}
