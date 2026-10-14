<?php

namespace App\Console\Commands;

use App\Jobs\Discord\SyncRoles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:discord')]
#[Description('Synchronise Discord roles and users')]
class SyncDiscord extends Command
{
    /**
     * Dispatch SyncRoles, which syncs the guild's roles and then batches a SyncUser job per user.
     */
    public function handle(): void
    {
        SyncRoles::dispatch();
    }
}
