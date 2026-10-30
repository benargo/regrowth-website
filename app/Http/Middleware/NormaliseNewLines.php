<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Support\Str;

/**
 * Converts CRLF and CR line endings in string input to LF.
 */
class NormaliseNewLines extends TransformsRequest
{
    /**
     * Transform the given value.
     *
     * @param  string  $key
     */
    protected function transform($key, mixed $value): mixed
    {
        return is_string($value) ? Str::replace(["\r\n", "\r"], "\n", $value) : $value;
    }
}
