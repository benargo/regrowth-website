<?php

namespace App\Actions\Phase;

use App\Models\Phase;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Applies a partial update to a phase. Only the keys present in the validated
 * data are written, and a key given as null clears its column.
 */
class UpdatePhase
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data  The request's validated data.
     */
    public function handle(Phase $phase, array $data): void
    {
        $phase->update($data);
    }
}
