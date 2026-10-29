<?php

namespace App\Http\Integrations\Discord\Middleware;

use App\Http\Integrations\Discord\RateLimitCache;
use App\Http\Integrations\ServedFromCache;
use JsonException;
use Saloon\Http\Response;

/**
 * Records the rate limits Discord reports on live responses, ignoring responses served from
 * the cache. A 429 blocks its route (or every route when global) for `retry_after`; an
 * exhausted bucket blocks its route until `X-RateLimit-Reset-After`.
 */
final class RecordRateLimits
{
    public function __construct(
        private readonly RateLimitCache $rateLimits,
        private readonly ServedFromCache $servedFromCache,
    ) {}

    public function __invoke(Response $response): void
    {
        if ($this->servedFromCache->check($response)) {
            return;
        }

        $pendingRequest = $response->getPendingRequest();
        $route = self::route(
            $pendingRequest->getMethod()->value,
            ltrim($pendingRequest->getRequest()->resolveEndpoint(), '/'),
        );

        if ($response->status() === 429) {
            $this->recordTooManyRequests($response, $route);

            return;
        }

        $resetAfter = $response->header('X-RateLimit-Reset-After');

        if ($response->header('X-RateLimit-Remaining') === '0' && is_numeric($resetAfter)) {
            $this->rateLimits->blockRoute($route, (float) $resetAfter, 'user');
        }
    }

    /**
     * The rate-limit key for a request: method plus path, with every numeric segment
     * except channel, guild and webhook ids replaced by `{id}`.
     */
    public static function route(string $method, string $endpoint): string
    {
        $path = preg_replace('#(?<!channels|guilds|webhooks)/\d+#', '/{id}', '/'.ltrim($endpoint, '/'));

        return "{$method} {$path}";
    }

    private function recordTooManyRequests(Response $response, string $route): void
    {
        $body = self::body($response);
        $retryAfter = self::retryAfter($response, $body);
        $scope = self::scope($response);

        if (self::isGlobal($response, $body)) {
            $this->rateLimits->blockGlobally($retryAfter);

            return;
        }

        $this->rateLimits->blockRoute($route, $retryAfter, $scope);
    }

    /**
     * Seconds to wait, from the body's `retry_after` or else the `Retry-After` header, minimum 1.
     *
     * @param  array<string, mixed>|null  $body
     */
    public static function retryAfter(Response $response, ?array $body): float
    {
        $seconds = $body['retry_after'] ?? $response->header('Retry-After');

        return is_numeric($seconds) ? max(1.0, (float) $seconds) : 1.0;
    }

    /**
     * `X-RateLimit-Scope` (`user`, `global` or `shared`); `user` when Discord omits it.
     */
    public static function scope(Response $response): string
    {
        $scope = $response->header('X-RateLimit-Scope');

        return is_string($scope) && $scope !== '' ? $scope : 'user';
    }

    /**
     * Whether the 429 applies to every route, from the body's `global` flag or a `global` scope.
     *
     * @param  array<string, mixed>|null  $body
     */
    public static function isGlobal(Response $response, ?array $body): bool
    {
        return ($body['global'] ?? false) === true || self::scope($response) === 'global';
    }

    /**
     * The decoded JSON response body, or null when it is not a JSON object.
     *
     * @return array<string, mixed>|null
     */
    public static function body(Response $response): ?array
    {
        try {
            $body = $response->json();
        } catch (JsonException) {
            return null;
        }

        return is_array($body) ? $body : null;
    }
}
