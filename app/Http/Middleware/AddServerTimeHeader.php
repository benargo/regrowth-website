<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddServerTimeHeader
{
    /**
     * Set the X-Server-Time header to the current Unix timestamp on the response.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Server-Time', (string) now()->getTimestamp());

        return $response;
    }
}
