<?php

namespace App\Listeners;

use App\Events\ModelPruned;
use App\Models\PrunedModel;

class RecordPrunedModel
{
    /**
     * Handle the event.
     *
     * When a model is pruned, record its ID and FQCN in the pruned_models table
     * to enable returning 410 Gone responses for requests to pruned models.
     */
    public function handle(ModelPruned $event): void
    {
        PrunedModel::create([
            'id' => $event->model->getKey(),
            'type' => get_class($event->model),
        ]);
    }
}
