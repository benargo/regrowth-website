<?php

namespace App\Contracts\Http\Integrations\Blizzard;

interface Mirrorable
{
    /**
     * Returns the relative disk path to use when mirroring this request's asset,
     * overriding the default URL-derived path from `MirrorPaths`.
     */
    public function resolveMirrorPath(): string;
}
