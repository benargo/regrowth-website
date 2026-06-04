<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

class CharacterNotFoundException extends NotFoundException
{
    protected string $prefix = 'Character not found:';
}
