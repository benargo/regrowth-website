<?php

namespace App\Actions\Datasets;

use App\Contracts\Models\EditLockable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes or renews a dataset record's edit lock for an active officer and
 * returns the edit lock props shared by its edit page and setup wizard. The
 * page polls for these alone, which is also what keeps the lock alive.
 */
class ResolveEditLock
{
    use AsAction;

    /**
     * A poll from an idle page (X-Edit-Idle) only reports whether the officer
     * could edit, so an unattended tab lets the lock expire instead of
     * holding it for ever.
     *
     * @return array{canEdit: bool, editor: Closure(): ?string}
     */
    public function handle(Request $request, Model&EditLockable $record): array
    {
        $canEdit = $request->hasHeader('X-Edit-Idle')
            ? ! $record->isLockedForEditingBy($request->user())
            : $record->acquireEditLock($request->user());

        return [
            'canEdit' => $canEdit,
            'editor' => fn (): ?string => $canEdit ? null : $record->editor()?->display_name,
        ];
    }
}
