<?php

namespace App\Console\Commands;

use App\Jobs\SyncDiscordRoles as Job;
use Illuminate\Console\Command;

class SyncDiscordRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-discord-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise Discord roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Job::dispatch();
    }
}
