<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

class MediaNotFoundException extends NotFoundException
{
    protected string $prefix = 'Media not found:';
}
