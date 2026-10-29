<?php

namespace App\Http\Requests\Concerns;

use App\Contracts\Models\EditLockable;
use Closure;
use Illuminate\Validation\Validator;

/**
 * For form requests that change an edit-locked dataset record: rejects the
 * change with an "edit_lock" error while another officer holds the lock.
 */
trait ChecksEditLock
{
    /**
     * An after() validation hook that adds the edit_lock error when someone
     * other than the current user holds the lock on the record bound to the
     * given route parameter.
     *
     * @param  string  $routeParameter  The route parameter bound to the EditLockable record, e.g. "gameVersion".
     * @param  string  $recordName  The record type as it reads in the message, e.g. "game version".
     * @return Closure(Validator): void
     */
    protected function editLockCheck(string $routeParameter, string $recordName): Closure
    {
        return function (Validator $validator) use ($routeParameter, $recordName): void {
            /** @var EditLockable $record */
            $record = $this->route($routeParameter);

            if ($record->isLockedForEditingBy($this->user())) {
                $validator->errors()->add('edit_lock', "Someone else is editing this {$recordName}. Your change was not saved.");
            }
        };
    }
}
