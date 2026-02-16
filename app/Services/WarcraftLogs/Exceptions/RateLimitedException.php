<?php

namespace App\Services\WarcraftLogs\Exceptions;

use Exception;

class RateLimitedException extends Exception
{
    public function __construct(string $message = 'WarcraftLogs API rate limit exceeded. Requests are paused for one hour.')
    {
        parent::__construct($message);
    }
}
