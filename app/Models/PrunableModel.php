<?php

namespace App\Models;

use App\Events\ModelPruned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

abstract class PrunableModel extends Model
{
    use Prunable;

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     *
     * @throws LogicException
     */
    abstract public function prunable(): Builder;

    /**
     * Dispatch a ModelPruned event before this event is deleted.
     */
    protected function pruning(): void
    {
        ModelPruned::dispatch($this);
    }

    /**
     * Resolve route binding, returning 410 Gone if the event has been pruned.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $event = parent::resolveRouteBinding($value, $field);

        if ($event === null && PrunedModel::where('id', $value)->where('type', static::class)->exists()) {
            throw new GoneHttpException('The requested resource has been pruned and is no longer available.');
        }

        return $event;
    }
}
