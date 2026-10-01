<?php

namespace App\Models\Concerns;

use App\Contracts\Models\EditLockable;
use App\Models\User;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gives a model a cache-backed edit lock, so one officer at a time can edit
 * it. Hosts implement EditLockable.
 *
 * @phpstan-require-implements EditLockable
 */
trait HasEditLock
{
    /**
     * How long an officer keeps the edit lock after their last active visit or poll.
     */
    public const int EDIT_LOCK_SECONDS = 300;

    public function editLock(User $user): Lock
    {
        return Cache::lock($this->editLockKey('editing'), static::EDIT_LOCK_SECONDS, (string) $user->id);
    }

    public function acquireEditLock(User $user): bool
    {
        $lock = $this->editLock($user);

        if (! $lock->get() && ! $lock->refresh()) {
            return false;
        }

        Cache::put($this->editLockKey('editor'), $user->id, static::EDIT_LOCK_SECONDS);

        return true;
    }

    public function isLockedForEditingBy(User $user): bool
    {
        $lock = $this->editLock($user);

        return $lock->isLocked() && ! $lock->isOwnedByCurrentProcess();
    }

    /**
     * Determine whether any officer holds the edit lock.
     */
    public function isBeingEdited(): bool
    {
        return Cache::has($this->editLockKey('editor'));
    }

    public function editor(): ?User
    {
        return User::find(Cache::get($this->editLockKey('editor')));
    }

    /**
     * Build a cache key scoped to this record's edit lock.
     */
    private function editLockKey(string $suffix): string
    {
        $prefix = Str::replace('_', '-', $this->getTable());

        return "{$prefix}.{$this->getKey()}.{$suffix}";
    }
}
