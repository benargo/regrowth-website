<?php

namespace App\Events;

use App\Models\DiscordRole;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscordRoleUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public DiscordRole $discordRole) {}
}
