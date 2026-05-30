<?php

namespace App\Contracts\Http\Integrations\Blizzard;

interface MirrorsAssets
{
    /**
     * Returns the publicly accessible URL for the mirrored asset, or null if mirroring failed.
     */
    public function mirroredUrl(): ?string;

    /**
     * Returns the relative disk path where the asset is stored (e.g. `blizzard-cdn/icons/56/foo.jpg`),
     * or null if the asset has not been mirrored yet.
     */
    public function mirroredPath(): ?string;
}
