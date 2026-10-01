<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tells whether other records still reference a model, which guards deletes.
 * The host declares a USAGE_RELATIONS constant listing the relationships
 * whose existing rows mark it as in use.
 */
trait TracksUsage
{
    /**
     * Determine whether any record in the host's USAGE_RELATIONS references this model.
     */
    public function isInUse(): bool
    {
        return collect(static::USAGE_RELATIONS)
            ->contains(fn (string $relation): bool => $this->{$relation}()->exists());
    }

    /**
     * Load a {relation}_count attribute for every relation in USAGE_RELATIONS.
     */
    #[Scope]
    protected function withUsageCounts(Builder $query): void
    {
        $query->withCount(static::USAGE_RELATIONS);
    }
}
