<?php

namespace Tests\Unit\Http\Integrations\Concerns;

use App\Http\Integrations\Concerns\CachesResponses;
use App\Http\Integrations\Concerns\HasCacheAwareRateLimits;
use App\Http\Integrations\ServedFromCache;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Enums\PipeOrder;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Tests\TestCase;

#[Group('platform')]
class HasCacheAwareRateLimitsTest extends TestCase
{
    #[Test]
    public function it_serves_a_cached_response_while_a_limit_is_exceeded(): void
    {
        Saloon::fake([CacheableRateLimitProbeRequest::class => MockResponse::make([], 200)]);
        $connector = new RateLimitProbeConnector(new ServedFromCache, Limit::allow(1)->everyMinute());

        $connector->send(new CacheableRateLimitProbeRequest);
        $response = $connector->send(new CacheableRateLimitProbeRequest);

        $this->assertTrue($response->isCached());
        Saloon::assertSentCount(1);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_still_blocks_a_live_request_while_a_limit_is_exceeded(): void
    {
        Saloon::fake([RateLimitProbeRequest::class => MockResponse::make([], 200)]);
        $connector = new RateLimitProbeConnector(new ServedFromCache, Limit::allow(1)->everyMinute());

        $connector->send(new RateLimitProbeRequest);

        $this->expectException(RateLimitReachedException::class);

        $connector->send(new RateLimitProbeRequest);
    }

    #[Test]
    public function it_does_not_sleep_for_a_cached_response_on_a_sleeping_limit(): void
    {
        Saloon::fake([CacheableRateLimitProbeRequest::class => MockResponse::make([], 200)]);
        $connector = new RateLimitProbeConnector(new ServedFromCache, Limit::allow(1)->everySeconds(1)->sleep());

        $connector->send(new CacheableRateLimitProbeRequest);
        $response = $connector->send(new CacheableRateLimitProbeRequest);

        $this->assertTrue($response->isCached());
        $this->assertNull($response->getPendingRequest()->delay()->get());
    }
}

/**
 * Minimal connector with one configurable cache-aware rate limit.
 */
class RateLimitProbeConnector extends Connector
{
    use HasCacheAwareRateLimits;

    public function __construct(
        private readonly ServedFromCache $servedFromCache,
        private readonly Limit $limit,
    ) {
        $this->middleware()->onRequest($servedFromCache, 'servedFromCache', PipeOrder::FIRST);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://probe.test';
    }

    protected function servedFromCache(): ServedFromCache
    {
        return $this->servedFromCache;
    }

    /**
     * @return array<int, Limit>
     */
    protected function resolveLimits(): array
    {
        return [$this->limit];
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new LaravelCacheStore(Cache::store());
    }
}

/**
 * Minimal uncached request.
 */
class RateLimitProbeRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/live';
    }
}

/**
 * Minimal cacheable request stored under the `probe` cache tag.
 */
class CacheableRateLimitProbeRequest extends Request implements Cacheable
{
    use CachesResponses;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/cached';
    }

    public function cacheExpiryInSeconds(): int
    {
        return 3600;
    }

    /**
     * @return array<int, string>
     */
    protected function cacheTags(): array
    {
        return ['probe'];
    }
}
