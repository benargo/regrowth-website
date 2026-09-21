<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use InvalidArgumentException;

class RealmRequiredException extends InvalidArgumentException
{
    public function __construct(BlizzardNamespace $namespace)
    {
        parent::__construct("A realm is required for the \"{$namespace->value}\" Blizzard namespace.");
    }
}
