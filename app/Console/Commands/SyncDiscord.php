<?php

namespace App\Console\Commands;

use App\Jobs\Discord\SyncChannels;
use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUsers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

#[Signature('sync:discord')]
#[Description('Synchronise Discord channels, roles and users')]
class SyncDiscord extends Command
{
    /**
     * Dispatch a chain that syncs the guild's channels, roles and then every user.
     */
    public function handle(): void
    {
        Bus::chain([new SyncChannels, new SyncRoles, new SyncUsers])->dispatch();
    }
}
