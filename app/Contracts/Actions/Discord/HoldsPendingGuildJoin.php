<?php

namespace App\Contracts\Actions\Discord;

interface HoldsPendingGuildJoin
{
    /**
     * The session key holding a pending guild join, as the user's Discord ID
     * under `user_id` and their encrypted access token under `token`.
     */
    public const string SESSION_KEY = 'discord.pending_join';
}
