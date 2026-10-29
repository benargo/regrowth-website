<?php

namespace App\Actions\Phase;

use App\Models\Phase;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the order of a game version's unscheduled phases: the first id gets
 * sort order 1. Each phase is saved on its own, so its model events fire.
 */
class ReorderPhases
{
    use AsAction;

    /**
     * @param  list<int>  $phaseIds
     */
    public function handle(array $phaseIds): void
    {
        $phases = Phase::whereKey($phaseIds)->get()->keyBy('id');

        DB::transaction(function () use ($phaseIds, $phases): void {
            foreach (array_values($phaseIds) as $index => $phaseId) {
                $phases->get($phaseId)?->update(['sort_order' => $index + 1]);
            }
        });
    }
}
