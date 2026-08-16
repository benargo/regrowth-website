<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureItemSlugIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $item = $request->route('item');
        $slug = $request->route('slug');

        $correctSlug = $item->slug ?: "item-{$item->id}";

        if ($correctSlug !== $slug) {
            return redirect()->route($request->route()->getName(), ['item' => $item->id, 'slug' => $correctSlug], 303);
        }

        return $next($request);
    }
}
