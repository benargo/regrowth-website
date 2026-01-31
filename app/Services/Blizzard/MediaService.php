<?php

namespace App\Services\Blizzard;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MediaService extends Service
{
    protected string $basePath = '/data/wow';

    /**
     * Default cache TTL values in seconds.
     */
    protected const CACHE_TTL_MEDIA = 604800; // 7 days

    protected const CACHE_TTL_SEARCH = 3600; // 1 hour

    /**
     * Base URL pattern for fetching icons by name.
     * Use sprintf with region and icon name.
     */
    protected const ICON_BASE_URL = 'https://render.worldofwarcraft.com/%s/icons/56/%s.jpg';

    /**
     * Allowed media tags for retrieval.
     */
    protected array $valid_tags = ['item', 'spell', 'playable-class'];

    public function __construct(
        protected Client $client,
        protected ?FilesystemManager $filesystem = null,
    ) {
        parent::__construct($client->withNamespace('static-eu'));
        $this->filesystem ??= app(FilesystemManager::class);
    }

    /**
     * Get media (icon URLs) by media ID.
     *
     * @return array<string, mixed>
     */
    public function find(string $tag, int $mediaId): array
    {
        $this->validateTags($tag);

        return $this->cacheable(
            $this->mediaCacheKey($tag, $mediaId),
            self::CACHE_TTL_MEDIA,
            fn () => $this->getJson("/media/{$tag}/{$mediaId}")
        );
    }

    /**
     * Search for media by name.
     *
     * @param array{tags?: array<string>, itemId?: integer, name?: string, $orderBy?: string, $page?: integer} $params
     * @return array<string, mixed>
     */
    public function search(array $params = []): array
    {
        if (isset($params['tags'])) {
            $this->validateTags($params['tags']);
        } else {
            throw new InvalidArgumentException('The "tags" parameter is required for media search.');
        }

        $query = $this->buildSearchQuery($params);

        return $this->cacheable(
            $this->searchCacheKey($query),
            self::CACHE_TTL_SEARCH,
            fn () => $this->getJson('/search/media', $query)
        );
    }

    /**
     * Build the search query parameters.
     *
     * @param array{tags: array<string>, itemId?: integer, name?: string, $orderBy?: string, page?: integer, pageSize?: integer} $params
     * @return array<string, mixed>
     */
    protected function buildSearchQuery(array $params): array
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
     * Get the cache key for media.
     */
    protected function mediaCacheKey(string $type, int $mediaId): string
    {
        return sprintf(
            'blizzard.media.%s.%s.%d',
            $type,
            $this->getCurrentNamespace(),
            $mediaId
        );
    }

    /**
     * Get the cache key for a search query.
     */
    protected function searchCacheKey(array $params): string
    {
        ksort($params);

        return sprintf(
            'blizzard.search.media.%s.%s',
            $this->getCurrentNamespace(),
            md5(serialize($params))
        );
    }

    /**
     * Get the current namespace for cache key generation.
     */
    protected function getCurrentNamespace(): string
    {
        return $this->client->getNamespace() ?? 'static-us';
    }

    /**
     * Validate that the given tag(s) are allowed.
     *
     * @param  string|array<string>  $tags
     *
     * @throws InvalidArgumentException
     */
    protected function validateTags(string|array $tags): void
    {
        $tagsToValidate = is_array($tags) ? $tags : [$tags];

        $invalidTags = array_diff($tagsToValidate, $this->valid_tags);

        if (count($invalidTags) > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid tag(s): %s. Allowed tags are: %s',
                    implode(', ', $invalidTags),
                    implode(', ', $this->valid_tags)
                )
            );
        }
    }

    /**
     * Get the icon URL by icon name.
     *
     * This constructs the URL directly without needing to query the API.
     */
    public function getIconUrl(string $iconName): string
    {
        return sprintf(
            self::ICON_BASE_URL,
            $this->client->getRegion()->value,
            $iconName
        );
    }

    /**
     * Download and store an icon by name.
     *
     * @return string|null The local path to the stored icon, or null on failure
     */
    public function downloadIconByName(string $iconName): ?string
    {
        $url = $this->getIconUrl($iconName);
        $path = sprintf('blizzard/icons/%s.jpg', $iconName);

        if ($this->disk()->exists($path)) {
            return $path;
        }

        $content = $this->fetchRemoteImage($url);

        if ($content === null) {
            return null;
        }

        $this->disk()->put($path, $content, 'public');

        return $path;
    }

    /**
     * Get the public URL for an icon by name.
     * Downloads the icon if not already stored.
     */
    public function getIconUrlByName(string $iconName): ?string
    {
        $path = $this->downloadIconByName($iconName);

        return $path !== null ? $this->disk()->url($path) : null;
    }

    /**
     * Download and store assets from a media response.
     *
     * @param  array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $assets
     * @return array<int, string|null> Map of file_data_id to local path
     */
    public function downloadAssets(array $assets): array
    {
        // Normalize to array of assets
        if (isset($assets['file_data_id'])) {
            $assets = [$assets];
        }

        $results = [];

        foreach ($assets as $asset) {
            if (! isset($asset['file_data_id'], $asset['value'])) {
                continue;
            }

            $fileDataId = (int) $asset['file_data_id'];
            $url = $asset['value'];
            $extension = $this->extractExtension($url);
            $path = $this->buildAssetPath($fileDataId, $extension);

            if ($this->disk()->exists($path)) {
                $results[$fileDataId] = $path;

                continue;
            }

            $content = $this->fetchRemoteImage($url);

            if ($content === null) {
                $results[$fileDataId] = null;

                continue;
            }

            $this->disk()->put($path, $content, 'public');
            $results[$fileDataId] = $path;
        }

        return $results;
    }

    /**
     * Get public URLs for stored assets.
     * Downloads the assets if not already stored.
     *
     * @param  array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $assets
     * @return array<int, string|null> Map of file_data_id to public URL
     */
    public function getAssetUrls(array $assets): array
    {
        $paths = $this->downloadAssets($assets);

        $urls = [];
        foreach ($paths as $fileDataId => $path) {
            $urls[$fileDataId] = $path !== null ? $this->disk()->url($path) : null;
        }

        return $urls;
    }

    /**
     * Check if assets are already stored locally.
     *
     * @param  array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $assets
     * @return array<int, bool> Map of file_data_id to existence status
     */
    public function assetsExist(array $assets): array
    {
        // Normalize to array of assets
        if (isset($assets['file_data_id'])) {
            $assets = [$assets];
        }

        $results = [];

        foreach ($assets as $asset) {
            if (! isset($asset['file_data_id'], $asset['value'])) {
                continue;
            }

            $fileDataId = (int) $asset['file_data_id'];
            $url = $asset['value'];
            $extension = $this->extractExtension($url);
            $path = $this->buildAssetPath($fileDataId, $extension);

            $results[$fileDataId] = $this->disk()->exists($path);
        }

        return $results;
    }

    /**
     * Get the configured filesystem disk instance.
     */
    protected function disk(): Filesystem
    {
        $diskName = config('services.blizzard.filesystem', 'public');

        return $this->filesystem->disk($diskName);
    }

    /**
     * Build the storage path for an asset.
     */
    protected function buildAssetPath(int $fileDataId, string $extension): string
    {
        return sprintf('blizzard/media/%d.%s', $fileDataId, $extension);
    }

    /**
     * Extract the file extension from a URL.
     */
    protected function extractExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension ?: 'jpg';
    }

    /**
     * Fetch the image content from a remote URL.
     */
    protected function fetchRemoteImage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            return null;
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }
}
