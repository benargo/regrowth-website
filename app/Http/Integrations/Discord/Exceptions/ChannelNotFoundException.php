<?php

namespace App\Http\Integrations\Discord\Exceptions;

/**
 * 404 with Discord error code 10003 (Unknown Channel).
 */
class ChannelNotFoundException extends NotFoundException
{
    protected string $prefix = 'Channel not found:';
}
