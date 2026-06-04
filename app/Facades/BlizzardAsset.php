<?php

namespace App\Facades;

use App\Http\Integrations\Blizzard\RenderConnector;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Saloon\Http\Response send(\Saloon\Http\Request $request)
 *
 * @see RenderConnector
 */
class BlizzardAsset extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RenderConnector::class;
    }
}
