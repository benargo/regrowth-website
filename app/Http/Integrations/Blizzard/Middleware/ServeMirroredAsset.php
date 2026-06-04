<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use App\Contracts\Http\Integrations\Blizzard\Mirrorable;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\Faking\FakeResponse;
use Saloon\Http\PendingRequest;

class ServeMirroredAsset implements RequestMiddleware
{
    public function __construct(
        private readonly MirrorPaths $resolver,
        private readonly Filesystem $disk,
    ) {}

    /**
     * Return a cached FakeResponse when the asset exists on disk, or null to pass through to the network.
     */
    public function __invoke(PendingRequest $pendingRequest): ?FakeResponse
    {
        $path = $this->resolvePath($pendingRequest);

        if ($path === null) {
            return null;
        }

        if (! $this->disk->exists($path)) {
            return null;
        }

        return new FakeResponse(
            body: $this->disk->get($path) ?? '',
            status: 200,
            headers: ['X-Mirror' => 'hit'],
        );
    }

    /**
     * Resolve the mirror disk path: honour a Mirrorable override first, then fall back to the URL-derived path.
     */
    private function resolvePath(PendingRequest $pendingRequest): ?string
    {
        $request = $pendingRequest->getRequest();

        if ($request instanceof Mirrorable) {
            return $request->resolveMirrorPath();
        }

        return $this->resolver->fromUrl($pendingRequest->getUrl());
    }
}
