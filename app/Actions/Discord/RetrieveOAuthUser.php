<?php

namespace App\Actions\Discord;

use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Retrieves the authenticated user from the Discord OAuth callback.
 */
class RetrieveOAuthUser
{
    use AsAction;

    public function __construct(
        protected Socialite $socialite,
    ) {}

    public function handle(): User
    {
        return $this->socialite->driver('discord')->user();
    }
}
