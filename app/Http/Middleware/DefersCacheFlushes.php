<?php

namespace App\Http\Middleware;

use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefersCacheFlushes implements SharesDeferredCacheFlushes
{
    /**
     * Mark the request as deferring cache flushes while the rest of the
     * pipeline runs, then run each flush the listeners recorded once, before
     * the response is sent. Recorded flushes run even when the pipeline throws.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::DEFERRING_KEY, true);

        try {
            return $next($request);
        } finally {
            $request->attributes->remove(self::DEFERRING_KEY);

            foreach ($request->attributes->get(self::PENDING_KEY, []) as $flush) {
                $flush();
            }

            $request->attributes->remove(self::PENDING_KEY);
        }
    }
}
