<?php

namespace App\Services\Blizzard;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

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
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Default search cache TTL values in seconds.
     */
    protected const SEARCH_CACHE_TTL = 3600; // 1 hour

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
