<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tells whether other records still reference a model, which guards deletes.
 * The host lists the relationships whose existing rows mark it as in use.
 */
trait TracksUsage
{
    /**
     * Relationships whose existing rows mark this model as in use.
     *
     * @return list<string>
     */
    abstract public function usageRelations(): array;

    /**
     * Determine whether any record in the host's usage relations references this model.
     */
    public function isInUse(): bool
    {
        return collect($this->usageRelations())
            ->contains(fn (string $relation): bool => $this->{$relation}()->exists());
    }

    /**
     * Load a {relation}_count attribute for every one of the host's usage relations.
     */
    #[Scope]
    protected function withUsageCounts(Builder $query): void
    {
        $query->withCount($this->usageRelations());
    }
}
