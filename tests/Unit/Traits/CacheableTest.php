<?php

namespace Tests\Unit\Traits;

use App\Exceptions\CacheException;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheableClass
{
    use Cacheable;

    public function getCachedValue(string $key, ?int $ttl, callable $callback): mixed
    {
        return $this->cacheable($key, $ttl, $callback);
    }
}

class CacheableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_cacheable_caches_result(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        };

        $result1 = $service->getCachedValue('test_key', 60, $callback);
        $result2 = $service->getCachedValue('test_key', 60, $callback);

        $this->assertEquals(['data' => 'test'], $result1);
        $this->assertEquals(['data' => 'test'], $result2);
        $this->assertEquals(1, $callCount);
    }

    public function test_cacheable_uses_default_ttl_when_null(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('default_ttl_key', 3600, \Mockery::type('callable'))
            ->andReturn(['cached' => true]);

        $service = new CacheableClass;

        $result = $service->getCachedValue('default_ttl_key', null, fn () => ['cached' => true]);

        $this->assertEquals(['cached' => true], $result);
    }

    public function test_cacheable_uses_provided_ttl(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('custom_ttl_key', 120, \Mockery::type('callable'))
            ->andReturn(['cached' => true]);

        $service = new CacheableClass;

        $result = $service->getCachedValue('custom_ttl_key', 120, fn () => ['cached' => true]);

        $this->assertEquals(['cached' => true], $result);
    }

    public function test_fresh_with_true_forgets_cache_and_refreshes(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'value_'.$callCount];
        };

        // First call caches the result
        $result1 = $service->getCachedValue('fresh_key', 60, $callback);
        $this->assertEquals(['data' => 'value_1'], $result1);
        $this->assertEquals(1, $callCount);

        // Second call returns cached result
        $result2 = $service->getCachedValue('fresh_key', 60, $callback);
        $this->assertEquals(['data' => 'value_1'], $result2);
        $this->assertEquals(1, $callCount);

        // Call with fresh() forces refresh
        $result3 = $service->fresh()->getCachedValue('fresh_key', 60, $callback);
        $this->assertEquals(['data' => 'value_2'], $result3);
        $this->assertEquals(2, $callCount);
    }

    public function test_fresh_resets_after_single_use(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'value_'.$callCount];
        };

        // Call with fresh()
        $service->fresh()->getCachedValue('reset_key', 60, $callback);
        $this->assertEquals(1, $callCount);

        // Next call should use cache again (fresh was reset)
        $service->getCachedValue('reset_key', 60, $callback);
        $this->assertEquals(1, $callCount);
    }

    public function test_fresh_with_false_only_returns_cached_data(): void
    {
        $service = new CacheableClass;

        // First, cache a value
        Cache::put('cached_only_key', ['data' => 'cached']);

        $callback = fn () => ['data' => 'fresh'];

        $result = $service->fresh(false)->getCachedValue('cached_only_key', 60, $callback);

        $this->assertEquals(['data' => 'cached'], $result);
    }

    public function test_fresh_with_false_throws_exception_when_cache_not_found(): void
    {
        $service = new CacheableClass;

        $callback = fn () => ['data' => 'fresh'];

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage("Cache key 'missing_key' not found after explicit cache use.");

        $service->fresh(false)->getCachedValue('missing_key', 60, $callback);
    }

    public function test_fresh_with_false_resets_after_single_use(): void
    {
        $service = new CacheableClass;

        Cache::put('reset_cached_key', ['data' => 'cached']);

        // First call with fresh(false)
        $service->fresh(false)->getCachedValue('reset_cached_key', 60, fn () => ['data' => 'fresh']);

        // Next call should behave normally (fresh was reset)
        $callCount = 0;
        $result = $service->getCachedValue('new_key', 60, function () use (&$callCount) {
            $callCount++;

            return ['data' => 'new'];
        });

        $this->assertEquals(['data' => 'new'], $result);
        $this->assertEquals(1, $callCount);
    }

    public function test_ignore_cache_bypasses_cache_entirely(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'value_'.$callCount];
        };

        // First call with ignoreCache bypasses cache
        $result1 = $service->ignoreCache()->getCachedValue('ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_1'], $result1);
        $this->assertEquals(1, $callCount);

        // Second call uses normal caching (ignoreCache was reset)
        $result2 = $service->getCachedValue('ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_2'], $result2);
        $this->assertEquals(2, $callCount);

        // Third call returns cached result
        $result3 = $service->getCachedValue('ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_2'], $result3);
        $this->assertEquals(2, $callCount);
    }

    public function test_fresh_is_fluent(): void
    {
        $service = new CacheableClass;

        $result = $service->fresh();

        $this->assertSame($service, $result);
    }

    public function test_fresh_with_null_is_fluent(): void
    {
        $service = new CacheableClass;

        $result = $service->fresh(null);

        $this->assertSame($service, $result);
    }

    public function test_fresh_with_false_is_fluent(): void
    {
        $service = new CacheableClass;

        $result = $service->fresh(false);

        $this->assertSame($service, $result);
    }

    public function test_ignore_cache_is_fluent(): void
    {
        $service = new CacheableClass;

        $result = $service->ignoreCache();

        $this->assertSame($service, $result);
    }

    public function test_fresh_with_null_uses_default_caching_behavior(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'value'];
        };

        $service->fresh(null)->getCachedValue('null_fresh_key', 60, $callback);
        $service->getCachedValue('null_fresh_key', 60, $callback);

        // Both calls should use normal caching (callback called once)
        $this->assertEquals(1, $callCount);
    }

    public function test_ignore_cache_does_not_store_in_cache(): void
    {
        $service = new CacheableClass;

        $callback = fn () => ['data' => 'value'];

        $service->ignoreCache()->getCachedValue('no_store_key', 60, $callback);

        $this->assertFalse(Cache::has('no_store_key'));
    }

    public function test_ignore_cache_resets_after_single_use(): void
    {
        $service = new CacheableClass;

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'value_'.$callCount];
        };

        $service->ignoreCache();

        // First call bypasses cache (result not stored)
        $result1 = $service->getCachedValue('reset_ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_1'], $result1);
        $this->assertEquals(1, $callCount);

        // Second call uses normal caching (ignoreCache was reset, cache miss, computes and stores)
        $result2 = $service->getCachedValue('reset_ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_2'], $result2);
        $this->assertEquals(2, $callCount);

        // Third call returns cached result from second call
        $result3 = $service->getCachedValue('reset_ignore_key', 60, $callback);
        $this->assertEquals(['data' => 'value_2'], $result3);
        $this->assertEquals(2, $callCount);
    }
}
