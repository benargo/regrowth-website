<?php

namespace App\Console\Commands;

use App\Jobs\BuildAddonExportFile as BuildAddonExportFileJob;
use Illuminate\Console\Command;

class BuildAddonExportFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prep-addon-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepares Regrowth Addon data by dispatching the export job';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        BuildAddonExportFileJob::dispatch();
    }
}
