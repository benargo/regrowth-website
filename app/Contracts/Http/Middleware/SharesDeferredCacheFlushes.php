<?php

namespace App\Contracts\Http\Middleware;

interface SharesDeferredCacheFlushes
{
    /**
     * The request attribute set while cache flushes are deferred.
     */
    public const string DEFERRING_KEY = 'cache-flushes:deferring';

    /**
     * The request attribute holding the deferred flushes, keyed by the listener that requested them.
     */
    public const string PENDING_KEY = 'cache-flushes:pending';
}
