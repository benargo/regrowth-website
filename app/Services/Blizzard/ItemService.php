<?php

namespace App\Services\Blizzard;

use Illuminate\Support\Facades\Cache;

class ItemService extends Service
{
    protected string $basePath = '/data/wow';

    public function __construct(
        protected Client $client,
    ) {
        parent::__construct($client->withNamespace('static-classicann-eu'));
    }

    /**
     * Find an item by its ID.
     *
     * @return array<string, mixed>
     */
    public function find(int $itemId): array
    {
        return $this->cacheable(
            $this->itemCacheKey($itemId),
            2628000, // 1 month
            fn () => $this->getJson("/item/{$itemId}")
        );
    }

    /**
     * Get media (icon URLs) for an item.
     *
     * @return array<string, mixed>
     */
    public function media(int $itemId): array
    {
        return app(MediaService::class)->find('item', $itemId);
    }

    /**
     * Search for items.
     *
     * @param  array{name?: string, orderby?: string, page?: int, pageSize?: int}  $params
     * @return array<string, mixed>
     */
    public function search(array $params = []): array
    {
        $query = $this->buildSearchQuery($params);

        return $this->cacheable(
            $this->searchCacheKey($query),
            3600, // 1 hour
            fn () => $this->getJson('/search/item', $query)
        );
    }

    /**
     * Build the search query parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildSearchQuery(array $params): array
    {
        $query = [];

        if (isset($params['name'])) {
            $query['name.en_GB'] = $params['name'];
        }

        if (isset($params['orderby'])) {
            $query['orderby'] = $params['orderby'];
        }

        if (isset($params['page'])) {
            $query['_page'] = (int) $params['page'];
        }

        if (isset($params['pageSize'])) {
            $query['_pageSize'] = min((int) $params['pageSize'], 1000);
        }

        return $query;
    }

    /**
     * Get the cache key for an item.
     */
    protected function itemCacheKey(int $itemId): string
    {
        return sprintf(
            'blizzard.item.%s.%d',
            $this->getCurrentNamespace(),
            $itemId
        );
    }

    /**
     * Get the cache key for a search query.
     *
     * @param  array<string, mixed>  $params
     */
    protected function searchCacheKey(array $params): string
    {
        ksort($params);

        return sprintf(
            'blizzard.search.item.%s.%s',
            $this->getCurrentNamespace(),
            md5(serialize($params))
        );
    }

    /**
     * Get the current namespace for cache key generation.
     */
    protected function getCurrentNamespace(): string
    {
        return $this->client->getNamespace() ?? 'static-classic-eu';
    }
}
