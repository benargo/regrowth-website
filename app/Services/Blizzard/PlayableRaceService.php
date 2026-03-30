<?php

namespace App\Services\Blizzard;

use App\Services\Blizzard\Exceptions\InvalidRaceException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

class PlayableRaceService extends Service
{
    /**
     * The base path for this service's API endpoints.
     */
    protected string $basePath = '/data/wow';

    /**
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 2592000; // 30 days

    /**
     * Constructor to initialize the Blizzard API client with the appropriate namespace
     */
    public function __construct(Client $client)
    {
        parent::__construct($client->withNamespace('static-classicann-eu'));
    }

    /**
     * Get the index of all playable races with their IDs and media.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return Cache::remember(
            $this->indexCacheKey(),
            $this->cacheTtl,
            fn () => $this->getJson('/playable-race/index')
        );
    }

    /**
     * Find a playable race by its ID.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidRaceException
     * @throws RequestException
     */
    public function find(int $playableRaceId): array
    {
        try {
            return Cache::remember(
                $this->findCacheKey($playableRaceId),
                $this->cacheTtl,
                fn () => $this->getJson("/playable-race/{$playableRaceId}")
            );
        } catch (RequestException $e) {
            if ($e->response->status() === 404 && $e->response->json('type') === 'BLZWEBAPI00000404') {
                throw new InvalidRaceException("Playable race {$playableRaceId} not found.", 404, $e);
            }

            throw $e;
        }
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
