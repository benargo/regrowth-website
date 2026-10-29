<?php

namespace App\Contracts\Models;

use App\Models\User;
use Illuminate\Cache\Lock;

/**
 * A model that one officer at a time may edit. Implemented by using the
 * App\Models\Concerns\HasEditLock trait.
 */
interface EditLockable
{
    /**
     * How long an officer keeps the edit lock after their last active visit or poll.
     */
    public const int EDIT_LOCK_SECONDS = 300;

    /**
     * The atomic lock that gives one officer at a time the right to edit this record.
     */
    public function editLock(User $user): Lock;

    /**
     * Take or extend the edit lock for the user, returning whether they hold it.
     */
    public function acquireEditLock(User $user): bool;

    /**
     * Determine whether an officer other than the given user holds the edit lock.
     */
    public function isLockedForEditingBy(User $user): bool;

    /**
     * Determine whether any officer holds the edit lock.
     */
    public function isBeingEdited(): bool;

    /**
     * The officer who last took or refreshed the edit lock, for display only.
     */
    public function editor(): ?User;
}
