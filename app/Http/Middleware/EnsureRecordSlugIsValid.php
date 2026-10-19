<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecordSlugIsValid
{
    /**
     * Redirect, with a 303 and the same query string, to the current route
     * with the canonical slug of the record in the given route parameter
     * when the requested slug differs from it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        $record = $request->route($parameter);

        if ($request->route('slug') !== $record->slug) {
            return redirect()->route(
                $request->route()->getName(),
                [...$request->query(), $parameter => $record, 'slug' => $record->slug],
                303,
            );
        }

        return $next($request);
    }
}
