<?php

namespace App\Console\Commands;

use App\Jobs\RaidHelper\FetchEvents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

#[Signature('raid-helper:fetch-events')]
#[Description('Fetch events from Raid Helper')]
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
