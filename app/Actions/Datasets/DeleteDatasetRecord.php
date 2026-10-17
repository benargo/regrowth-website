<?php

namespace App\Actions\Datasets;

use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes a dataset record, unless another officer holds its edit lock or
 * other records still reference it. Returns the refusal message, or null once
 * the record is deleted.
 */
class DeleteDatasetRecord
{
    use AsAction;

    /**
     * @param  string  $recordName  The record as it reads in the refusal message, e.g. "Phase 3.5".
     */
    public function handle(User $user, Model&EditLockable&HasUsage $record, string $recordName): ?string
    {
        if ($record->isLockedForEditingBy($user)) {
            return "Someone is editing {$recordName}, so it can't be deleted right now.";
        }

        if ($record->isInUse()) {
            return "{$recordName} is still linked to other records and cannot be deleted.";
        }

        $record->delete();

        return null;
    }
}
