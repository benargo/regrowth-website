<?php

namespace App\Services\Blizzard;

use Illuminate\Support\Arr;

class PlayableClassService extends Service
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
     * Get the index of all playable classes with their IDs and media.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return $this->cacheable(
            $this->indexCacheKey(),
            $this->cacheTtl,
            fn () => $this->getJson('/playable-class/index')
        );
    }

    /**
     * Find a playable class by its ID.
     *
     * @return array<string, mixed>
     */
    public function find(int $playableClassId): array
    {
        return $this->cacheable(
            $this->findCacheKey($playableClassId),
            $this->cacheTtl,
            fn () => $this->getJson("/playable-class/{$playableClassId}")
        );
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
     * Get the icon URL for a playable class.
     */
    public function iconUrl(int $playableClassId): ?string
    {
        $mediaService = app(MediaService::class);

        $media = $mediaService->find('playable-class', $playableClassId);

        $assets = $mediaService->getAssetUrls($media['assets'] ?? []);

        $fileDataId = Arr::get($media, 'assets.0.file_data_id');

        return $fileDataId !== null ? Arr::get($assets, $fileDataId) : null;
    }

    /**
     * Get the cache key for the index.
     */
    protected function indexCacheKey(): string
    {
        return sprintf(
            'blizzard.playable-class.index.%s',
            $this->getNamespace()
        );
    }

    /**
     * Get the cache key for a playable class.
     */
    protected function findCacheKey(int $playableClassId): string
    {
        return sprintf(
            'blizzard.playable-class.%d.%s',
            $playableClassId,
            $this->getNamespace(),
        );
    }
}
