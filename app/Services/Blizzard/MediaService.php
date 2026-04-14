<?php

namespace App\Services\Blizzard;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Http;

class MediaService
{
    private const BLIZZARD_BASE_URL = 'https://render.worldofwarcraft.com/%s/icons/56/%s.jpg';

    /**
     * @param  string  $region  The Blizzard API region (e.g. 'eu', 'us').
     * @param  string  $diskName  The filesystem disk name to use for storage.
     */
    public function __construct(
        private string $region,
        private FilesystemManager $filesystem,
        private string $diskName = 'public',
    ) {}

    // ==================== Public API ====================

    /**
     * Get local URL(s) for media assets, downloading from Blizzard if needed.
     *
     * Pass a string icon name for a single asset, or an array of asset data
     * from a Blizzard media API response for multiple assets.
     *
     * @param  string|array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $media
     * @return string|array<int, string|null>|null Single URL, or map of file_data_id to URL
     */
    public function get(string|array $media): string|array|null
    {
        if (is_string($media)) {
            $path = $this->downloadByName($media);

            return $path !== null ? $this->disk()->url($path) : null;
        }

        $paths = $this->downloadAssets($media);

        $urls = [];
        foreach ($paths as $fileDataId => $path) {
            $urls[$fileDataId] = $path !== null ? $this->disk()->url($path) : null;
        }

        return $urls;
    }

    /**
     * Download media assets to local storage, fetching from Blizzard if needed.
     *
     * Pass a string icon name for a single asset, or an array of asset data
     * from a Blizzard media API response for multiple assets.
     *
     * @param  string|array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $media
     * @return string|array<int, string|null>|null Single path, or map of file_data_id to path
     */
    public function download(string|array $media): string|array|null
    {
        if (is_string($media)) {
            return $this->downloadByName($media);
        }

        return $this->downloadAssets($media);
    }

    // ==================== Helpers ====================

    /**
     * Download a single media icon by name.
     */
    private function downloadByName(string $name): ?string
    {
        $url = $this->getRemoteUrl($name);
        $path = sprintf('blizzard/icons/%s.jpg', $name);

        if ($this->disk()->exists($path)) {
            return $path;
        }

        $content = $this->fetchRemoteMedia($url);

        if ($content === null) {
            return null;
        }

        $this->disk()->put($path, $content, 'public');

        return $path;
    }

    /**
     * Download multiple assets from a media API response.
     *
     * @param  array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $assets
     * @return array<int, string|null>
     */
    private function downloadAssets(array $assets): array
    {
        $assets = $this->normalizeAssets($assets);
        $results = [];

        foreach ($assets as $asset) {
            $fileDataId = (int) $asset['file_data_id'];
            $path = $this->buildAssetPath($fileDataId, $this->extractExtension($asset['value']));

            if ($this->disk()->exists($path)) {
                $results[$fileDataId] = $path;

                continue;
            }

            $content = $this->fetchRemoteMedia($asset['value']);

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
     * Build the remote Blizzard media URL from an icon name.
     */
    private function getRemoteUrl(string $name): string
    {
        return sprintf(self::BLIZZARD_BASE_URL, $this->region, $name);
    }

    /**
     * Fetch media content from a remote URL.
     */
    private function fetchRemoteMedia(string $url): ?string
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

    /**
     * Normalize a single or multiple asset array into a consistent format.
     *
     * @param  array{key: string, value: string, file_data_id: int}|array<array{key: string, value: string, file_data_id: int}>  $assets
     * @return array<array{key: string, value: string, file_data_id: int}>
     */
    private function normalizeAssets(array $assets): array
    {
        if (isset($assets['file_data_id'])) {
            $assets = [$assets];
        }

        return array_filter($assets, fn (mixed $asset) => is_array($asset) && isset($asset['file_data_id'], $asset['value']));
    }

    /**
     * Get the configured filesystem disk instance.
     */
    private function disk(): Filesystem
    {
        return $this->filesystem->disk($this->diskName);
    }

    /**
     * Build the local storage path for an asset.
     */
    private function buildAssetPath(int $fileDataId, string $extension): string
    {
        return sprintf('blizzard/media/%d.%s', $fileDataId, $extension);
    }

    /**
     * Extract the file extension from a URL.
     */
    private function extractExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension ?: 'jpg';
    }
}
