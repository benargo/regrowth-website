<?php

namespace Tests\Unit\Http\Integrations;

use App\Http\Integrations\Concerns\CachesResponses;
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
use Tests\TestCase;

#[Group('platform')]
class ServedFromCacheTest extends TestCase
{
    #[Test]
    public function it_marks_a_response_served_from_the_cache(): void
    {
        Saloon::fake([CacheableServedFromCacheProbeRequest::class => MockResponse::make([], 200)]);
        $servedFromCache = new ServedFromCache;
        $connector = new ServedFromCacheProbeConnector($servedFromCache);

        $connector->send(new CacheableServedFromCacheProbeRequest);
        $response = $connector->send(new CacheableServedFromCacheProbeRequest);

        $this->assertTrue($response->isCached());
        $this->assertTrue($servedFromCache->check($response));
    }

    #[Test]
    public function it_does_not_mark_a_live_response(): void
    {
        Saloon::fake([CacheableServedFromCacheProbeRequest::class => MockResponse::make([], 200)]);
        $servedFromCache = new ServedFromCache;

        $response = (new ServedFromCacheProbeConnector($servedFromCache))->send(new CacheableServedFromCacheProbeRequest);

        $this->assertFalse($servedFromCache->check($response));
    }

    #[Test]
    public function it_does_not_mark_a_mocked_response_for_an_uncacheable_method(): void
    {
        Saloon::fake([CacheableServedFromCacheProbeRequest::class => MockResponse::make([], 200)]);
        $servedFromCache = new ServedFromCache;
        $connector = new ServedFromCacheProbeConnector($servedFromCache);

        $connector->send(new CacheableServedFromCacheProbeRequest(Method::POST));
        $response = $connector->send(new CacheableServedFromCacheProbeRequest(Method::POST));

        $this->assertFalse($servedFromCache->check($response));
    }

    #[Test]
    public function it_resolves_the_pending_request_of_a_response(): void
    {
        Saloon::fake([CacheableServedFromCacheProbeRequest::class => MockResponse::make([], 200)]);
        $servedFromCache = new ServedFromCache;
        $connector = new ServedFromCacheProbeConnector($servedFromCache);

        $connector->send(new CacheableServedFromCacheProbeRequest);
        $response = $connector->send(new CacheableServedFromCacheProbeRequest);

        $this->assertTrue($servedFromCache->check($response->getPendingRequest()));
    }

    #[Test]
    public function it_stores_responses_under_the_given_cache_tags(): void
    {
        Saloon::fake([CacheableServedFromCacheProbeRequest::class => MockResponse::make([], 200)]);
        $connector = new ServedFromCacheProbeConnector(new ServedFromCache);

        $connector->send(new CacheableServedFromCacheProbeRequest);
        Cache::tags(['probe'])->flush();
        $response = $connector->send(new CacheableServedFromCacheProbeRequest);

        $this->assertFalse($response->isCached());
        Saloon::assertSentCount(2);
    }
}

/**
 * Minimal connector that marks requests served from the cache.
 */
class ServedFromCacheProbeConnector extends Connector
{
    public function __construct(ServedFromCache $servedFromCache)
    {
        $this->middleware()->onRequest($servedFromCache, 'servedFromCache', PipeOrder::FIRST);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://probe.test';
    }
}

/**
 * Minimal cacheable request stored under the `probe` cache tag.
 */
class CacheableServedFromCacheProbeRequest extends Request implements Cacheable
{
    use CachesResponses;

    public function __construct(Method $method = Method::GET)
    {
        $this->method = $method;
    }

    public function resolveEndpoint(): string
    {
        return '/probe';
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
