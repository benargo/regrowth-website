<?php

namespace App\Services\Blizzard;

use App\Services\Blizzard\Exceptions\InvalidClassException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class PlayableClassService extends Service
{
    /**
     * The base path for the Playable Class API endpoints.
     */
    protected string $basePath = '/data/wow';

    /**
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 2592000; // 30 days

    /**
     * Constructor to initialize the Blizzard API client with the appropriate namespace for playable classes.
     */
    public function __construct(Client $client)
    {
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
     *
     * @throws InvalidClassException
     * @throws RequestException
     */
    public function find(int $playableClassId): array
    {
        try {
            return $this->cacheable(
                $this->findCacheKey($playableClassId),
                $this->cacheTtl,
                fn () => $this->getJson("/playable-class/{$playableClassId}")
            );
        } catch (RequestException $e) {
            if ($e->response->status() === 404 && $e->response->json('type') === 'BLZWEBAPI00000404') {
                throw new InvalidClassException("Playable class {$playableClassId} not found.", 404, $e);
            }

            throw $e;
        }
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
