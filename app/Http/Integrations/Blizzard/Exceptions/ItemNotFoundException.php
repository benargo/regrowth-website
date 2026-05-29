<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

class ItemNotFoundException extends NotFoundException
{
    protected string $prefix = 'Item not found:';
}
