<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use Illuminate\Support\Uri;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\PendingRequest;

class MergeUriQuery implements RequestMiddleware
{
    public function __construct(private readonly Uri $uri) {}

    public function __invoke(PendingRequest $pendingRequest): void
    {
        $pendingRequest->query()->merge($this->uri->query()->all());
    }
}
