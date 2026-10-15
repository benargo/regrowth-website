<?php

namespace App\Http\Integrations\Discord\Exceptions;

/**
 * 404 with Discord error code 10008 (Unknown Message).
 */
class MessageNotFoundException extends NotFoundException
{
    protected string $prefix = 'Message not found:';
}
