<?php

namespace App\Services\Blizzard;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

abstract class Service
{
    /**
     * The base path for this service's API endpoints.
     */
    protected string $basePath = '';

    /**
     * Fields selected for the next request.
     *
     * @var array<int, string>
     */
    protected array $selectedFields = [];

    /**
     * Whether to ignore cache for the next request.
     */
    protected bool $ignoreCache = false;

    public function __construct(
        protected Client $client,
    ) {}

    /**
     * Make a GET request to the API.
     *
     * @param  array<string, mixed>  $query
     */
    protected function get(string $path, array $query = []): Response
    {
        /**
         * Reset ignoreCache after use
         */
        if ($this->ignoreCache) {
            $this->ignoreCache = false;
        }

        return $this->client->http()->get($this->buildPath($path), $query);
    }

    /**
     * Make a GET request and return the JSON response as an array.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getJson(string $path, array $query = []): array
    {
        $apiResponse = $this->get($path, $query)->throw()->json();

        if (! empty($this->selectedFields)) {
            $apiResponse = $this->selectFields($apiResponse, $this->selectedFields);

            // Reset selectedFields after use
            $this->selectedFields = [];
        }

        return $apiResponse;
    }

    /**
     * Select specific fields from an array, supporting dot notation for nested values.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function selectFields(array $data, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $value = Arr::get($data, $field);

            if ($value !== null) {
                Arr::set($result, $field, $value);
            }
        }

        return $result;
    }

    /**
     * Build the full API path by combining base path and endpoint path.
     */
    protected function buildPath(string $path): string
    {
        $basePath = rtrim($this->basePath, '/');
        $path = ltrim($path, '/');

        if ($basePath === '') {
            return $path;
        }

        return $basePath.'/'.$path;
    }

    /**
     * Select specific fields for the next request.
     */
    public function select(string ...$fields): static
    {
        $this->selectedFields = $fields;

        return $this;
    }

    /**
     * Disable caching for the next request.
     */
    public function fresh(): static
    {
        $this->ignoreCache = true;

        return $this;
    }

    /**
     * Cache the result of a callback, unless caching is disabled.
     *
     * @param  int|null  $ttl  Time to live in seconds, or null for default
     */
    protected function cacheable(string $cacheKey, ?int $ttl, callable $callback): mixed
    {
        if ($this->ignoreCache) {
            return $callback();
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Set a custom namespace for this service's requests.
     */
    public function withNamespace(string $namespace): static
    {
        $this->client->withNamespace($namespace);

        return $this;
    }

    /**
     * Get the current namespace for cache key generation.
     */
    protected function getNamespace(): string
    {
        return $this->client->getNamespace() ?? '';
    }

    /**
     * Get the underlying client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
