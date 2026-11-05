<?php

namespace App\Actions\RecruitmentMacros;

use App\Models\RecruitmentMacro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores a new order for the live recruitment macros: the first id gets sort order 1.
 */
class ReorderRecruitmentMacros
{
    use AsAction;

    /**
     * @param  list<string>  $macroIds
     *
     * @throws ValidationException When the ids don't match the live macros exactly.
     */
    public function handle(array $macroIds): void
    {
        DB::transaction(function () use ($macroIds): void {
            $liveIds = RecruitmentMacro::pluck('id')->all();

            if (count($macroIds) !== count($liveIds) || array_diff($liveIds, $macroIds) !== []) {
                throw ValidationException::withMessages([
                    'macro_ids' => 'The macro list has changed; reload the page and try again.',
                ]);
            }

            RecruitmentMacro::setNewOrder($macroIds);
        });
    }
}
