<?php

namespace App\Services\Blizzard;

use Illuminate\Support\Facades\Cache;

class PlayableClassService extends Service
{
    protected string $basePath = '/data/wow';

    /**
     * Default cache TTL values in seconds.
     */
    protected const CACHE_TTL_INDEX = 604800; // 7 days

    public function __construct(
        protected Client $client,
    ) {
        parent::__construct($client->withNamespace('static-classic-eu'));
    }

    /**
     * Get the index of all playable classes with their IDs and media.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return Cache::remember(
            $this->indexCacheKey(),
            self::CACHE_TTL_INDEX,
            fn () => $this->getJson('/media/playable-class/index')
        );
    }

    /**
     * Get the index without caching (fresh from API).
     *
     * @return array<string, mixed>
     */
    public function indexFresh(): array
    {
        Cache::forget($this->indexCacheKey());

        return $this->getJson('/media/playable-class/index');
    }

    /**
     * Get media (icon URLs) for a playable class.
     *
     * @return array<string, mixed>
     */
    public function media(int $playableClassId): array
    {
        return app(MediaService::class)->find('playable-class', $playableClassId);
    }

    /**
     * Get the cache key for the index.
     */
    protected function indexCacheKey(): string
    {
        return sprintf(
            'blizzard.playable-class.index.%s',
            $this->getCurrentNamespace()
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
