<?php

namespace App\Http\Middleware;

use App\Http\Resources\Concerns\HasScopes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetResourceScope
{
    /**
     * Apply the given scope to every instance of the given resource class
     * for the rest of the request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  class-string  $resource
     */
    public function handle(Request $request, Closure $next, string $resource, string $scope): Response
    {
        if (! in_array(HasScopes::class, class_uses_recursive($resource), true)) {
            Log::error("Resource [{$resource}] does not support scopes.");

            return $next($request);
        }

        $resource::setRequestScope($scope);

        try {
            return $next($request);
        } finally {
            $resource::clearRequestScope();
        }
    }
}
