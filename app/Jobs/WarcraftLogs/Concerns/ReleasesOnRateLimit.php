<?php

namespace App\Jobs\WarcraftLogs\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

trait ReleasesOnRateLimit
{
    /**
     * Keep retrying rate-limit releases until the points window has passed.
     * The job's #[MaxExceptions] and #[FailOnTimeout] still fail it after real exceptions or a timeout.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * Release the job back onto the queue until the Warcraft Logs points window resets.
     */
    private function releaseUntilPointsReset(RateLimitReachedException $exception, string $context): void
    {
        $limit = $exception->getLimit();
        $seconds = $limit->getRemainingSeconds();

        Log::warning("Warcraft Logs rate limit reached while fetching {$context}; releasing for {$seconds} seconds.", [
            'limit' => $limit->getName(),
        ]);

        $this->release($seconds);
    }
}
