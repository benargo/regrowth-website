<?php

namespace App\Facades;

use App\Http\Integrations\Blizzard\Support\MirrorPathResolver;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null fromUrl(string $url)
 * @method static bool validateHost(string $host)
 *
 * @see MirrorPathResolver
 */
class BlizzardRenderPath extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MirrorPathResolver::class;
    }
}
