<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

class InvalidClassException extends NotFoundException
{
    protected string $prefix = 'Playable class not found:';
}
