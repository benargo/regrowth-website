<?php

namespace App\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Saloon\Http\Response;

/**
 * The API key's points budget for the current hour. Internal bookkeeping for
 * MonitorRateLimit. Never cached, because a cached answer would describe a stale window.
 */
final class GetRateLimitDataRequest extends WarcraftLogsRequest
{
    public function __construct(WarcraftLogsNamespace $namespace)
    {
        parent::__construct($namespace);

        $this->disableCaching();
    }

    /**
     * Unused: caching is disabled in the constructor. Required by Cacheable.
     */
    public function cacheExpiryInSeconds(): int
    {
        return 0;
    }

    public function createDtoFromResponse(Response $response): RateLimitData
    {
        return RateLimitData::from($response->json('data.rateLimitData'));
    }

    protected function graphQLQuery(): string
    {
        return <<<'GRAPHQL'
            query GetRateLimitData {
                rateLimitData {
                    limitPerHour
                    pointsSpentThisHour
                    pointsResetIn
                }
            }
            GRAPHQL;
    }
}
