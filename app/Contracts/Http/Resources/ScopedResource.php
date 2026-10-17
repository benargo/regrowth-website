<?php

namespace App\Contracts\Http\Resources;

interface ScopedResource
{
    /**
     * Limit the output to the fields of the given scope.
     */
    public function scope(string $key): static;

    /**
     * The scopes available, keyed by name.
     *
     * @return array<string, list<string>>
     */
    public function scopes(): array;
}
