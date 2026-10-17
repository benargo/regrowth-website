<?php

namespace App\Attributes\Http\Controllers;

use App\Http\Middleware\SetResourceScope;
use Attribute;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Registers the `SetResourceScope` middleware on a controller or controller
 * method, scoping every instance of `$resource` to `$scope` for the request.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ScopesResourceTo extends Middleware
{
    /**
     * @param  class-string  $resource
     * @param  array<string>|null  $only
     * @param  array<string>|null  $except
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $scope,
        ?array $only = null,
        ?array $except = null,
    ) {
        parent::__construct(SetResourceScope::class.':'.$resource.','.$scope, $only, $except);
    }
}
