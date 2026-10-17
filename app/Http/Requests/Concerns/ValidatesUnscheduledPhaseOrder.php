<?php

namespace App\Http\Requests\Concerns;

use App\Models\Phase;
use Closure;
use Illuminate\Validation\Validator;

/**
 * For form requests that reorder a game version's unscheduled phases:
 * rejects a list that isn't exactly that version's unscheduled phases, such
 * as one sent by a page loaded before a phase was added, dated or moved.
 */
trait ValidatesUnscheduledPhaseOrder
{
    /**
     * An after() validation hook that adds an error to the attribute unless
     * its ids are exactly the unscheduled phases of the given game version
     * (or of no game version, when null).
     *
     * @return Closure(Validator): void
     */
    protected function unscheduledPhaseOrderCheck(string $attribute, ?int $gameVersionId): Closure
    {
        return function (Validator $validator) use ($attribute, $gameVersionId): void {
            if (! $this->has($attribute) || $validator->errors()->hasAny([$attribute, "{$attribute}.*", 'game_version_id'])) {
                return;
            }

            $expected = Phase::where('game_version_id', $gameVersionId)
                ->whereNull('start_date')
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $given = collect($this->input($attribute))
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($given !== $expected) {
                $validator->errors()->add($attribute, 'The unscheduled phases have changed since this page loaded. Reload the page and try again.');
            }
        };
    }
}
