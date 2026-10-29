<?php

namespace App\Http\Integrations;

use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use WeakMap;

/**
 * Request middleware that marks pending requests already answered by the response cache,
 * and reports whether a pending request or response was served from the cache.
 */
final class ServedFromCache
{
    /**
     * @var WeakMap<PendingRequest, true>
     */
    private WeakMap $pendingRequests;

    public function __construct()
    {
        $this->pendingRequests = new WeakMap;
    }

    /**
     * Marks the pending request when it already holds a fake response.
     */
    public function __invoke(PendingRequest $pendingRequest): void
    {
        if (! $pendingRequest->hasFakeResponse()) {
            return;
        }

        $this->pendingRequests[$pendingRequest] = true;
    }

    public function check(PendingRequest|Response $subject): bool
    {
        $pendingRequest = $subject instanceof Response ? $subject->getPendingRequest() : $subject;

        return isset($this->pendingRequests[$pendingRequest]);
    }
}
