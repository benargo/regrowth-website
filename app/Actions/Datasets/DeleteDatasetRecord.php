<?php

namespace App\Actions\Datasets;

use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes a dataset record from its Index page, unless another officer holds
 * its edit lock or other records still reference it. Redirects back with an
 * error when refused, or to the dataset's Index with a success message.
 */
class DeleteDatasetRecord
{
    use AsAction;

    /**
     * @param  string  $recordName  The record as it reads in the flash message, e.g. "Phase 3.5".
     * @param  string  $indexRoute  The dataset's Index route name, e.g. "management.phases.index".
     */
    public function handle(
        Request $request,
        Model&EditLockable&HasUsage $record,
        string $recordName,
        string $indexRoute,
    ): RedirectResponse {
        if ($record->isLockedForEditingBy($request->user())) {
            return Redirect::back()->with('error', "Someone is editing {$recordName}, so it can't be deleted right now.");
        }

        if ($record->isInUse()) {
            return Redirect::back()->with('error', "{$recordName} is still linked to other records and cannot be deleted.");
        }

        $record->delete();

        return Redirect::route($indexRoute)->with('success', "Deleted {$recordName}.");
    }
}
