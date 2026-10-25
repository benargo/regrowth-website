<?php

namespace App\Observers;

use App\Enums\DiscordAvatar;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "saved" event.
     *
     * Dispatches an avatar attach job for each Discord avatar hash that is set on creation or
     * changed to a new value, and clears the collection of each hash changed to null.
     */
    public function saved(User $user): void
    {
        foreach (DiscordAvatar::cases() as $avatar) {
            $attribute = $avatar->hashAttribute();
            $hash = $user->getAttribute($attribute);

            if ($user->wasRecentlyCreated) {
                if ($hash !== null) {
                    AttachAvatarToUser::dispatch($user->getKey(), $avatar);
                }

                continue;
            }

            if (! $user->wasChanged($attribute)) {
                continue;
            }

            if ($hash === null) {
                $user->clearMediaCollection($avatar->value);

                continue;
            }

            AttachAvatarToUser::dispatch($user->getKey(), $avatar);
        }
    }
}
