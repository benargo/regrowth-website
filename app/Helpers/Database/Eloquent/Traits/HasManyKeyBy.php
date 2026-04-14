<?php

namespace App\Helpers\Database\Eloquent\Traits;

use App\Helpers\Database\Eloquent\Relations\HasManyKeyBy as Relationship;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyKeyBy
{
    /**
     * @param  null  $foreignKey
     * @param  null  $localKey
     * @return HasMany
     */
    protected function hasManyKeyBy($keyBy, $related, $foreignKey = null, $localKey = null)
    {
        // copied from \Illuminate\Database\Eloquent\Concerns\HasRelationships::hasMany

        $instance = $this->newRelatedInstance($related);
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new Relationship(
            $keyBy,
            $instance->newQuery(),
            $this,
            $instance->getTable().'.'.$foreignKey,
            $localKey
        );
    }
}
