<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

class InvalidRaceException extends NotFoundException
{
    protected string $prefix = 'Playable race not found:';
}
