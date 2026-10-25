<?php

namespace App\Observers;

use App\Enums\DiscordAvatar;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * Dispatches an avatar attach job for each Discord avatar hash that is set.
     */
    public function created(User $user): void
    {
        foreach (DiscordAvatar::cases() as $avatar) {
            if ($user->getAttribute($avatar->hashAttribute()) !== null) {
                AttachAvatarToUser::dispatch($user->getKey(), $avatar);
            }
        }
    }

    /**
     * Handle the User "updated" event.
     *
     * Dispatches an avatar attach job for each Discord avatar hash changed to a new value,
     * and clears the collection of each hash changed to null.
     */
    public function updated(User $user): void
    {
        foreach (DiscordAvatar::cases() as $avatar) {
            $attribute = $avatar->hashAttribute();

            if (! $user->wasChanged($attribute)) {
                continue;
            }

            if ($user->getAttribute($attribute) !== null) {
                AttachAvatarToUser::dispatch($user->getKey(), $avatar);
            } else {
                $user->clearMediaCollection($avatar->value);
            }
        }
    }
}
