<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\Support\BattleNetSessionError;
use RuntimeException;
use Throwable;

class BattleNetSessionException extends RuntimeException
{
    public function __construct(public readonly BattleNetSessionError $error, ?Throwable $previous = null)
    {
        parent::__construct($error->message(), previous: $previous);
    }
}
