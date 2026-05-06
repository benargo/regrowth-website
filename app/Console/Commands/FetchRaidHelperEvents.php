<?php

namespace App\Console\Commands;

use App\Jobs\RaidHelper\FetchEvents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

#[Signature('app:fetch-raid-helper-events')]
#[Description('Command description')]
class FetchRaidHelperEvents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        Bus::dispatch(new FetchEvents);
        $this->info('Raid Helper events fetch job dispatched successfully.');
    }
}
