<?php

namespace App\Services\RaidHelper\Exceptions;

use Exception;

class NoEventsFoundException extends Exception
{
    /**
     * The exception message.
     */
    protected $message = 'No events found for the specified server.';
}
