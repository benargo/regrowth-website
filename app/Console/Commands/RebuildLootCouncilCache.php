<?php

namespace App\Console\Commands;

use App\Jobs\RebuildLootCouncilCacheJob;
use Illuminate\Console\Command;

class RebuildLootCouncilCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rebuild-loot-council-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuilds the Loot Council cache by dispatching a job.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dispatch(new RebuildLootCouncilCacheJob);
        $this->info('Loot Council cache rebuild job dispatched successfully.');
    }
}
