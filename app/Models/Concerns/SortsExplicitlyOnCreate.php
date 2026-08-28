<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * Complements {@see SortableTrait} so that an explicitly provided order-column
 * value survives creation instead of being replaced by the auto-incremented
 * "highest order number + 1".
 *
 * @mixin Model
 * @mixin Sortable
 */
trait SortsExplicitlyOnCreate
{
    use SortableTrait {
        shouldSortWhenCreating as shouldSortWhenCreatingFromConfig;
    }

    /**
     * Only auto-assign the order column when the caller has not set one.
     */
    public function shouldSortWhenCreating(): bool
    {
        if ($this->getAttribute($this->determineOrderColumnName()) !== null) {
            return false;
        }

        return $this->shouldSortWhenCreatingFromConfig();
    }
}
