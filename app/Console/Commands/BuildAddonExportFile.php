<?php

namespace App\Console\Commands;

use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Addon\BuildExportFiles;
use App\Models\GameVersion;
use Illuminate\Console\Command;

class BuildAddonExportFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prep-addon-data {--game-version= : Slug of a single game version to build}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepares Regrowth Addon data by dispatching the export jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $slug = $this->option('game-version');

        if ($slug === null) {
            BuildExportFiles::dispatch();

            return self::SUCCESS;
        }

        $gameVersion = GameVersion::firstWhere('slug', $slug);

        if ($gameVersion === null) {
            $this->error("Unknown game version: {$slug}");

            return self::FAILURE;
        }

        BuildExportFile::dispatch($gameVersion->id);

        return self::SUCCESS;
    }
}
