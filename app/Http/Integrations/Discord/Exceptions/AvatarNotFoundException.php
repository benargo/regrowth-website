<?php

namespace App\Http\Integrations\Discord\Exceptions;

/**
 * 404 from the Discord CDN for an avatar image.
 */
class AvatarNotFoundException extends NotFoundException
{
    protected string $prefix = 'Avatar not found:';
}
