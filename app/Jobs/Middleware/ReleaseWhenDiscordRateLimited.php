<?php

namespace App\Jobs\Middleware;

use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Releases a queued job or notification back onto the queue until Discord's rate limit resets.
 */
final class ReleaseWhenDiscordRateLimited
{
    public function handle(object $job, Closure $next): mixed
    {
        try {
            return $next($job);
        } catch (RateLimitedException $exception) {
            Log::warning('Discord rate limit reached; releasing the job until it resets.', [
                'job' => method_exists($job, 'displayName') ? $job->displayName() : $job::class,
                'method' => $exception->method,
                'endpoint' => $exception->endpoint,
                'retry_after' => $exception->retryAfter,
                'scope' => $exception->scope,
                'global' => $exception->global,
                'sent' => $exception->response !== null,
            ]);

            $job->release($exception->releaseAfter());

            return null;
        }
    }
}
