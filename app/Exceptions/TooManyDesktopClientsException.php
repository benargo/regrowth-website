<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user who already has 10 linked desktop clients signs in from another device.
 */
class TooManyDesktopClientsException extends Exception
{
    public function __construct()
    {
        parent::__construct('You have 10 desktop apps signed in. Sign out of one on your regrowth.gg account page, then try again.');
    }
}
