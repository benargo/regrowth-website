<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * A collection of scoped resources that applies one scope to every item.
 *
 * @property class-string<ScopedResource> $collects
 */
class ScopedResourceCollection extends AnonymousResourceCollection implements ScopedResource
{
    /**
     * Limit every item to the fields of the given scope. An undefined scope
     * is logged once and ignored.
     */
    public function scope(string $key): static
    {
        if (! array_key_exists($key, $this->scopes())) {
            Log::error("Scope [{$key}] is not defined on [{$this->collects}].");

            return $this;
        }

        foreach ($this->collection as $resource) {
            $resource->scope($key);
        }

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function scopes(): array
    {
        return $this->collects::definedScopes();
    }
}
