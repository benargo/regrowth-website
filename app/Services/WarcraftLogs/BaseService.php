<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Enums\Endpoints;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\RateLimitedException;
use App\Services\WarcraftLogs\Traits\RateLimited;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class BaseService
{
    use RateLimited;

    private int $cacheTtl = 3600;

    protected const BASE_CACHE_KEY = 'warcraftlogs';

    protected Endpoints $endpoint = Endpoints::WWW;

    protected AuthenticationHandler $auth;

    protected int $guildId;

    protected int $timeout = 30;

    /**
     * @param  array{client_id: string, client_secret: string, guild_id?: int}  $config
     */
    public function __construct(array $config, AuthenticationHandler $auth)
    {
        if (empty($config)) {
            $config = config('services.warcraftlogs');
        }

        $this->guildId = $config['guild_id'] ?? 0;
        $this->auth = $auth;
    }

    /**
     * Get a configured HTTP client for GraphQL requests.
     */
    protected function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->endpoint->url())
            ->withToken($this->auth->clientToken())
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? $this->timeout);
    }

    /**
     * Execute a GraphQL query and return the full HTTP response.
     * This method does not cache results.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws GraphQLException
     * @throws RateLimitedException
     */
    protected function query(string $query, array $variables = [], ?int $ttl = null, ?int $timeout = null): array
    {
        $this->ensureNotRateLimited();

        return Cache::remember(
            $this->queryCacheKey($query, $variables),
            $ttl ?? $this->cacheTtl,
            function () use ($query, $variables, $timeout) {
                $payload = ['query' => $query];

                if (! empty($variables)) {
                    $payload['variables'] = $variables;
                }

                $json = $this->send($payload, $timeout);

                if (isset($json['errors'])) {
                    throw new GraphQLException($json['errors']);
                }

                return $json['data'] ?? [];
            }
        );
    }

    /**
     * POST a GraphQL payload and return the decoded JSON body.
     *
     * A 401 means the cached client token has been revoked or has otherwise
     * become invalid, so the token is discarded and the request retried once
     * with a fresh one. A second 401 is rethrown as a genuine credential failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RateLimitedException
     * @throws RequestException
     */
    private function send(array $payload, ?int $timeout, bool $isRetry = false): array
    {
        try {
            $response = $this->http($timeout)->post('', $payload);
            $this->trackRateLimitHeaders($response);

            return $response->throw()->json() ?? [];
        } catch (RequestException $e) {
            if ($e->response->status() === 429) {
                $this->activateRateLimitCooldown();

                throw new RateLimitedException;
            }

            if ($e->response->status() === 401 && ! $isRetry) {
                $this->auth->forgetClientToken();

                return $this->send($payload, $timeout, isRetry: true);
            }

            throw $e;
        }
    }

    /**
     * Generate a unique cache key for a GraphQL query.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function queryCacheKey(string $query, array $variables): string
    {
        return static::BASE_CACHE_KEY.':'.md5($query.json_encode($variables));
    }
}
