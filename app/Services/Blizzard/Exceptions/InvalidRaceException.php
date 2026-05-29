<?php

namespace App\Services\Blizzard\Exceptions;

use Exception;

/**
 * TODO: When BlizzardService::findPlayableRace() is migrated to use BlizzardConnector,
 * this class should be removed in favour of
 * App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException, which extends
 * Saloon\Exceptions\Request\Statuses\NotFoundException and implements BlizzardRequestException.
 */
class InvalidRaceException extends Exception
{
    //
}
