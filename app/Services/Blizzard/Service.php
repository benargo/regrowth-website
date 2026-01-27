<?php

namespace App\Services\Blizzard;

use Illuminate\Http\Client\Response;

abstract class Service
{
    /**
     * The base path for this service's API endpoints.
     */
    protected string $basePath = '';

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
        return $this->get($path, $query)->throw()->json();
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
     * Set a custom namespace for this service's requests.
     */
    public function withNamespace(string $namespace): static
    {
        $this->client->withNamespace($namespace);

        return $this;
    }

    /**
     * Get the underlying client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
