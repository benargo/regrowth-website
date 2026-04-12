<?php

namespace App\Services\Blizzard;

use App\Events\GuildRosterFetched;
use App\Services\Blizzard\Exceptions\InvalidClassException;
use App\Services\Blizzard\Exceptions\InvalidRaceException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BlizzardService
{
    private string $profileNamespace;

    private string $staticNamespace;

    private string $mediaNamespace;

    /**
     * @param  array<string, mixed>  $config  The resolved services.blizzard config array.
     */
    public function __construct(
        private Client $client,
        private array $config,
    ) {
        $this->profileNamespace = $this->config('namespaces.profile');
        $this->staticNamespace = $this->config('namespaces.static');
        $this->mediaNamespace = $this->config('namespaces.media');
    }

    // ==================== Infrastructure ====================

    /**
     * Get a value from the resolved Blizzard config array.
     *
     * @throws InvalidArgumentException
     */
    private function config(string $key): mixed
    {
        $value = Arr::get($this->config, $key);

        if ($value === null) {
            throw new InvalidArgumentException("Missing Blizzard config key: {$key}");
        }

        return $value;
    }

    /**
     * Make a GET request to the Blizzard API and return JSON.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $namespace, string $path, array $query = []): array
    {
        return $this->client->http()
            ->withHeaders(['Battlenet-Namespace' => $namespace])
            ->get($path, $query)
            ->throw()
            ->json();
    }

    /**
     * Build a standardised cache key for a service method.
     */
    public function cacheKey(string $method, mixed ...$params): string
    {
        return sprintf('blizzard.%s.%s', $method, md5(serialize($params)));
    }

    /**
     * Get the underlying client instance.
     */
    protected function client(): Client
    {
        return $this->client;
    }

    // ==================== Characters ====================

    /**
     * Get character profile by realm and name.
     *
     * @return array<string, mixed>
     */
    public function getCharacterProfile(string $name, ?string $realm = null): array
    {
        $realm = $realm ?? $this->config('realm.slug');
        $name = Str::lower($name);

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getCharacterProfile', $realm, $name),
            21600, // 6 hours
            fn () => $this->getJson($this->profileNamespace, "/profile/wow/character/{$realm}/{$name}")
        );
    }

    /**
     * Get character status by realm and name.
     *
     * @return array<string, mixed>
     */
    public function getCharacterStatus(string $name, ?string $realm = null): array
    {
        $realm = $realm ?? $this->config('realm.slug');
        $name = Str::lower($name);

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getCharacterStatus', $realm, $name),
            21600, // 6 hours
            fn () => $this->getJson($this->profileNamespace, "/profile/wow/character/{$realm}/{$name}/status")
        );
    }

    // ==================== Guild ====================

    /**
     * Get guild roster by realm and guild slug.
     *
     * @return array<string, mixed>
     */
    public function getGuildRoster(?string $realmSlug = null, ?string $nameSlug = null): array
    {
        $realmSlug = $realmSlug ?? $this->config('realm.slug');
        $nameSlug = $nameSlug ?? $this->config('guild.slug');

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getGuildRoster', $realmSlug, $nameSlug),
            900, // 15 minutes
            function () use ($realmSlug, $nameSlug) {
                $roster = $this->getJson($this->profileNamespace, "/data/wow/guild/{$realmSlug}/{$nameSlug}/roster");

                GuildRosterFetched::dispatch($roster);

                return $roster;
            }
        );
    }

    // ==================== Playable Races ====================

    /**
     * Get the index of all playable races.
     *
     * @return array<string, mixed>
     */
    public function getPlayableRaces(): array
    {
        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getPlayableRaces'),
            2592000, // 30 days
            fn () => $this->getJson($this->staticNamespace, '/data/wow/playable-race/index')
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
    public function findPlayableRace(int $playableRaceId): array
    {
        try {
            return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
                $this->cacheKey('findPlayableRace', $playableRaceId),
                2592000, // 30 days
                fn () => $this->getJson($this->staticNamespace, "/data/wow/playable-race/{$playableRaceId}")
            );
        } catch (RequestException $e) {
            if ($e->response->status() === 404 && $e->response->json('type') === 'BLZWEBAPI00000404') {
                throw new InvalidRaceException("Playable race {$playableRaceId} not found.", 404, $e);
            }

            throw $e;
        }
    }

    // ==================== Playable Classes ====================

    /**
     * Get the index of all playable classes.
     *
     * @return array<string, mixed>
     */
    public function getPlayableClasses(): array
    {
        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getPlayableClasses'),
            2592000, // 30 days
            fn () => $this->getJson($this->staticNamespace, '/data/wow/playable-class/index')
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
    public function findPlayableClass(int $playableClassId): array
    {
        try {
            return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
                $this->cacheKey('findPlayableClass', $playableClassId),
                2592000, // 30 days
                fn () => $this->getJson($this->staticNamespace, "/data/wow/playable-class/{$playableClassId}")
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
    public function getPlayableClassMedia(int $playableClassId): array
    {
        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getPlayableClassMedia', $playableClassId),
            2592000, // 30 days
            fn () => $this->getJson($this->mediaNamespace, "/data/wow/media/playable-class/{$playableClassId}")
        );
    }

    // ==================== Items ====================

    /**
     * Find an item by its ID.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function findItem(int $itemId): array
    {
        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('findItem', $itemId),
            2628000, // 1 month
            fn () => $this->getJson($this->staticNamespace, "/data/wow/item/{$itemId}")
        );
    }

    /**
     * Get media (icon URLs) for an item.
     *
     * @return array<string, mixed>
     */
    public function getItemMedia(int $itemId): array
    {
        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('getItemMedia', $itemId),
            604800, // 7 days
            fn () => $this->getJson($this->mediaNamespace, "/data/wow/media/item/{$itemId}")
        );
    }

    /**
     * Search for items.
     *
     * @param  array{name?: string, orderby?: string, page?: int, pageSize?: int}  $params
     * @return array<string, mixed>
     */
    public function searchItems(array $params = []): array
    {
        $query = $this->buildItemSearchQuery($params);

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('searchItems', $query),
            3600, // 1 hour
            fn () => $this->getJson($this->staticNamespace, '/data/wow/search/item', $query)
        );
    }

    /**
     * Build the search query parameters for item search.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildItemSearchQuery(array $params): array
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

    // ==================== Media ====================

    /**
     * Allowed media tags for retrieval.
     */
    private const VALID_MEDIA_TAGS = ['item', 'spell', 'playable-class'];

    /**
     * Find media (icon URLs) by tag and media ID.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function findMedia(string $tag, int $mediaId): array
    {
        $this->validateMediaTags($tag);

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('findMedia', $tag, $mediaId),
            604800, // 7 days
            fn () => $this->getJson($this->mediaNamespace, "/data/wow/media/{$tag}/{$mediaId}")
        );
    }

    /**
     * Search for media.
     *
     * @param  array{tags?: array<string>, itemId?: int, name?: string, orderby?: string, page?: int, pageSize?: int}  $params
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function searchMedia(array $params = []): array
    {
        if (! isset($params['tags'])) {
            throw new InvalidArgumentException('The "tags" parameter is required for media search.');
        }

        $this->validateMediaTags($params['tags']);

        $query = $this->buildMediaSearchQuery($params);

        return Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $this->cacheKey('searchMedia', $query),
            3600, // 1 hour
            fn () => $this->getJson($this->mediaNamespace, '/data/wow/search/media', $query)
        );
    }

    /**
     * Build the search query parameters for media search.
     *
     * @param  array{tags: array<string>, itemId?: int, name?: string, orderby?: string, page?: int, pageSize?: int}  $params
     * @return array<string, mixed>
     */
    private function buildMediaSearchQuery(array $params): array
    {
        $query = [];

        if (isset($params['tags']) && is_array($params['tags'])) {
            $query['_tags'] = implode(',', $params['tags']);
        }

        if (isset($params['itemId'])) {
            $query['itemId'] = $params['itemId'];
        }

        if (isset($params['name'])) {
            $query['name.en_US'] = $params['name'];
        }

        if (isset($params['orderby'])) {
            $query['orderby'] = $params['orderby'];
        }

        if (isset($params['page'])) {
            $query['_page'] = $params['page'];
        }

        if (isset($params['pageSize'])) {
            $query['_pageSize'] = min((int) $params['pageSize'], 1000);
        }

        return $query;
    }

    /**
     * Validate that the given media tag(s) are allowed.
     *
     * @param  string|array<string>  $tags
     *
     * @throws InvalidArgumentException
     */
    private function validateMediaTags(string|array $tags): void
    {
        $tagsToValidate = is_array($tags) ? $tags : [$tags];

        $invalidTags = array_diff($tagsToValidate, self::VALID_MEDIA_TAGS);

        if (count($invalidTags) > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid tag(s): %s. Allowed tags are: %s',
                    implode(', ', $invalidTags),
                    implode(', ', self::VALID_MEDIA_TAGS)
                )
            );
        }
    }
}
