<?php

namespace App\Http\Integrations\WarcraftLogs\Middleware;

use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\Requests\GetRateLimitDataRequest;
use App\Http\Integrations\WarcraftLogs\Requests\WarcraftLogsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Support\Facades\Log;
use JsonException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Spatie\LaravelData\Exceptions\CannotCreateData;

/**
 * Watches the points headers on every live Warcraft Logs response.
 *
 * At or below 10% remaining, it logs a warning. Below 50%, and when no reset time is
 * cached yet, it asks `rateLimitData` once and caches the moment the points reset, so a
 * later 429 cools down for exactly the rest of the window. It only makes the HTTP call
 * and writes the cache.
 */
final class MonitorRateLimit
{
    private const float LOOKUP_THRESHOLD = 0.5;

    private const float WARNING_THRESHOLD = 0.1;

    public function __construct(
        private readonly RateLimitResetCache $rateLimitReset,
    ) {}

    public function __invoke(Response $response): void
    {
        if ($response->isCached()) {
            return;
        }

        $request = $response->getRequest();

        if (! $request instanceof WarcraftLogsRequest) {
            return;
        }

        if ($request instanceof GetRateLimitDataRequest) {
            return;
        }

        $limitHeader = $response->header('x-ratelimit-limit');

        if (! is_numeric($limitHeader)) {
            return;
        }

        $remainingHeader = $response->header('x-ratelimit-remaining');

        if (! is_numeric($remainingHeader)) {
            return;
        }

        $limit = (int) $limitHeader;
        $remaining = (int) $remainingHeader;

        if ($limit <= 0) {
            return;
        }

        $host = parse_url($response->getPendingRequest()->getUrl(), PHP_URL_HOST) ?: null;

        if ($remaining <= $limit * self::WARNING_THRESHOLD) {
            Log::warning(
                "Warcraft Logs API points are running low: {$remaining} of {$limit} remaining.",
                ['host' => $host, 'limit' => $limit, 'remaining' => $remaining],
            );
        }

        if ($remaining >= $limit * self::LOOKUP_THRESHOLD) {
            return;
        }

        if ($this->rateLimitReset->resetsAt() !== null) {
            return;
        }

        $this->recordResetTime($response, $request->namespace(), $host);
    }

    /**
     * A failed lookup (HTTP or GraphQL error, a body that is not JSON, or a payload
     * missing rateLimitData fields) is logged and swallowed. The bookkeeping must
     * never fail the response that triggered it.
     */
    private function recordResetTime(Response $response, WarcraftLogsNamespace $namespace, ?string $host): void
    {
        try {
            $rateLimit = $response->getConnector()->send(new GetRateLimitDataRequest($namespace))->dto();
        } catch (WarcraftLogsRequestException|FatalRequestException|RateLimitReachedException|JsonException|CannotCreateData $exception) {
            Log::warning(
                "Could not fetch Warcraft Logs rate limit data: {$exception->getMessage()}",
                ['host' => $host],
            );

            return;
        }

        $this->rateLimitReset->put($rateLimit);
    }
}
