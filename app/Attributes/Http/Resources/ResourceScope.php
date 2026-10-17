<?php

namespace App\Attributes\Http\Resources;

use Attribute;

/**
 * Declares a named scope on a resource class: the fields it keeps, and the
 * scope applied to each nested resource class.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ResourceScope
{
    /**
     * @param  list<string>  $fields
     * @param  array<class-string, string>  $mappings
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields = [],
        public readonly array $mappings = [],
    ) {}
}
