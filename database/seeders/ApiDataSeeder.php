<?php

namespace Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Throwable;

class ApiDataSeeder extends Seeder
{
    /**
     * The commands that fetch data from external APIs, in the order they must run.
     *
     * @var array<int, string>
     */
    private const FETCH_COMMANDS = [
        'sync:discord',
        'fetch:blizzard-roster',
        'fetch:warcraft-logs',
        'fetch:raid-helper',
    ];

    /**
     * Fetch live data from the external APIs when enabled via `app.seed_from_apis`.
     */
    public function run(): void
    {
        if (! config('app.seed_from_apis')) {
            return;
        }

        foreach (self::FETCH_COMMANDS as $command) {
            try {
                $exitCode = $this->command->call($command);
            } catch (Throwable $e) {
                $this->command->warn("{$command} failed: {$e->getMessage()}");

                continue;
            }

            if ($exitCode !== Command::SUCCESS) {
                $this->command->warn("{$command} failed with exit code {$exitCode}.");
            }
        }
    }
}
