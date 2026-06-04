<?php

namespace App\Facades;

use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null fromUrl(string $url)
 * @method static bool validateHost(string $host)
 *
 * @see MirrorPaths
 */
class BlizzardRenderPath extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MirrorPaths::class;
    }
}
