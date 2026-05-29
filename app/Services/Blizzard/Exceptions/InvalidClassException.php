<?php

namespace App\Services\Blizzard\Exceptions;

use Exception;

/**
 * TODO: When BlizzardService::findPlayableClass() is migrated to use BlizzardConnector,
 * this class should extend Saloon\Exceptions\Request\Statuses\NotFoundException and
 * implement BlizzardRequestException, following the constructor pattern used by
 * CharacterNotFoundException and ItemNotFoundException.
 */
class InvalidClassException extends Exception
{
    //
}
