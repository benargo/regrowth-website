<?php

namespace App\Actions\RecruitmentMacros;

use App\Models\RecruitmentMacro;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
            $live = RecruitmentMacro::pluck('id')->all();

            Validator::make(['macro_ids' => $macroIds], [
                'macro_ids' => [function (string $attribute, array $value, Closure $fail) use ($live): void {
                    if (count($value) !== count($live) || array_diff($value, $live) !== [] || array_diff($live, $value) !== []) {
                        $fail('The macro list has changed; reload the page and try again.');
                    }
                }],
            ])->validate();

            RecruitmentMacro::setNewOrder(array_values($macroIds));
        });
    }
}
