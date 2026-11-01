<?php

namespace App\Events;

use App\Contracts\Events\FlushesPermissionsCache;
use App\Models\DiscordRole;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscordRoleDeleted implements FlushesPermissionsCache, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public DiscordRole $discordRole) {}
}
