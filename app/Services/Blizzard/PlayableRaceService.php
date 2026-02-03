<?php

namespace App\Services\Blizzard;

class PlayableRaceService extends Service
{
    protected string $basePath = '/data/wow';

    /**
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 2592000; // 30 days

    public function __construct(
        protected Client $client,
    ) {
        parent::__construct($client->withNamespace('static-classicann-eu'));
    }

    /**
     * Get the index of all playable races with their IDs and media.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return $this->cacheable(
            $this->indexCacheKey(),
            $this->cacheTtl,
            fn () => $this->getJson('/playable-race/index')
        );
    }

    /**
     * Find a playable race by its ID.
     *
     * @return array<string, mixed>
     */
    public function find(int $playableRaceId): array
    {
        return $this->cacheable(
            $this->findCacheKey($playableRaceId),
            $this->cacheTtl,
            fn () => $this->getJson("/playable-race/{$playableRaceId}")
        );
    }

    /**
     * Get the cache key for the index.
     */
    protected function indexCacheKey(): string
    {
        return sprintf(
            'blizzard.playable-race.index.%s',
            $this->getNamespace()
        );
    }

    /**
     * Get the cache key for a playable race.
     */
    protected function findCacheKey(int $playableRaceId): string
    {
        return sprintf(
            'blizzard.playable-race.%d.%s',
            $playableRaceId,
            $this->getNamespace(),
        );
    }
}
