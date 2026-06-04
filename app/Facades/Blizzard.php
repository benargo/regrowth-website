<?php

namespace App\Facades;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Saloon\Http\Response send(\Saloon\Http\Request $request)
 *
 * @see BlizzardConnector
 */
class Blizzard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BlizzardConnector::class;
    }
}
