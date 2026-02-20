<?php

namespace App\Console\Commands;

use App\Models\TBC\DailyQuestNotification;
use Illuminate\Console\Command;

class ResetDailyQuests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-daily-quests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily quests on Discord by deleting the existing notification message.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting daily quests...');

        $notifications = DailyQuestNotification::all();

        if ($notifications->isEmpty()) {
            $this->info('No daily quest notification messages found. Task skipped.');

            return;
        }

        $notifications->each(function (DailyQuestNotification $notification) {
            // Deleting the model will trigger the job to delete the message from Discord
            $notification->delete();
        });
    }
}
