<?php

namespace App\Http\Integrations\Discord\Exceptions;

/**
 * 404 with Discord error code 10007 (Unknown Member).
 */
class UserNotInGuildException extends NotFoundException
{
    protected string $prefix = 'User is not a member of the guild:';
}
