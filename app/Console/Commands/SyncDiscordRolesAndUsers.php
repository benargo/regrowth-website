<?php

namespace App\Console\Commands;

use App\Jobs\SyncDiscordRoles;
use App\Jobs\SyncDiscordUsers;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncDiscordRolesAndUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-discord';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise Discord roles and users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Bus::batch([
            new SyncDiscordRoles,
            new SyncDiscordUsers,
        ])->then(function (Batch $batch) {
            Log::info('Discord roles and users synchronisation completed successfully.');
        })->catch(function (Batch $batch, Throwable $e) {
            Log::error('An error occurred during Discord roles and users synchronisation: '.$e->getMessage());
        })->dispatch();
    }
}
