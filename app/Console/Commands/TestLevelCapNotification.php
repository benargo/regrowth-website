<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Notifications\DiscordNotifiable;
use App\Notifications\LevelCapAchieved;
use Illuminate\Console\Command;

class TestLevelCapNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-level-cap-notification {names* : Character names to include in the test notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test Level 70 achievement notification to Discord.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var array<int, string> $names */
        $names = $this->argument('names');

        if (empty($names)) {
            $this->error('At least one character name is required.');

            return self::FAILURE;
        }

        // Look up existing characters or create test ones
        $characters = collect($names)->map(function (string $name) {
            return Character::firstOrCreate(
                ['name' => $name],
                ['reached_level_cap_at' => now()]
            );
        });

        DiscordNotifiable::channel('tbc_chat')->notify(
            new LevelCapAchieved($characters)
        );

        $this->info('Test notification sent to Discord tbc_chat channel.');
        $this->line('Character names: '.implode(', ', $names));

        return self::SUCCESS;
    }
}
