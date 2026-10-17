<?php

namespace App\Attributes\Http\Resources;

use Attribute;

/**
 * Marks a resource method as the definition behind a `scopeDefinitions()`
 * entry. The entry's key is the method name, or `$key` when given.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ScopeDefinition
{
    public function __construct(public readonly ?string $key = null) {}
}
