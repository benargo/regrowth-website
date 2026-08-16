<?php

namespace App\Console\Commands;

use App\Jobs\SyncDiscordRoles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:discord')]
#[Description('Synchronise Discord roles and users')]
class SyncDiscord extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // SyncDiscordUsers is dispatched automatically by DiscordServiceProvider
        // once SyncDiscordRoles completes.
        SyncDiscordRoles::dispatch();
    }
}
