<?php

namespace App\Http\Integrations\Blizzard\Responses;

use Illuminate\Support\Facades\Storage;
use Saloon\Http\Response;

class FetchAssetResponse extends Response
{
    private ?string $mirroredPath = null;

    public function setMirroredPath(string $path): void
    {
        $this->mirroredPath = $path;
    }

    public function mirroredPath(): ?string
    {
        return $this->mirroredPath;
    }

    public function mirroredUrl(): ?string
    {
        if ($this->mirroredPath === null) {
            return null;
        }

        return Storage::disk(config('services.blizzard.filesystem', 'public'))
            ->url($this->mirroredPath);
    }

    public function isFromMirror(): bool
    {
        return $this->header('X-Mirror') === 'hit';
    }
}
