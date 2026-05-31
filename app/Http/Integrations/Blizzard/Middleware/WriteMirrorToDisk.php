<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use App\Contracts\Http\Integrations\Blizzard\Mirrorable;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Saloon\Contracts\ResponseMiddleware;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Throwable;

class WriteMirrorToDisk implements ResponseMiddleware
{
    public function __construct(
        private readonly MirrorPaths $resolver,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(Response $response): void
    {
        $pendingRequest = $response->getPendingRequest();
        $path = $this->resolvePath($pendingRequest);

        if ($path === null) {
            return;
        }

        if ($response instanceof FetchAssetResponse) {
            $response->setMirroredPath($path);
        }

        if ($response->status() === 404) {
            throw new MediaNotFoundException(
                method: $pendingRequest->getMethod()->value,
                endpoint: $pendingRequest->getUrl(),
                blizzardStatus: 404,
                response: $response,
            );
        }

        if (! $response->successful()) {
            try {
                $response->throw();
            } catch (Throwable $e) {
                report($e);
            }

            return;
        }

        try {
            Cache::lock("blizzard-cdn:lock:{$path}", 30)->block(10, function () use ($path, $response): void {
                if ($this->disk->exists($path)) {
                    return;
                }

                $this->disk->put($path, $response->body());
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function resolvePath(PendingRequest $pendingRequest): ?string
    {
        $request = $pendingRequest->getRequest();

        if ($request instanceof Mirrorable) {
            return $request->resolveMirrorPath();
        }

        return $this->resolver->fromUrl($pendingRequest->getUrl());
    }
}
