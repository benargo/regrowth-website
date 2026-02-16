<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationDeleting;
use App\Jobs\DeleteDailyQuestNotificationFromDiscord as DeleteJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteDailyQuestNotificationFromDiscord implements ShouldQueue
{
    public function handle(DailyQuestNotificationDeleting $event): void
    {
        if (! $event->notification->discord_message_id) {
            return;
        }

        DeleteJob::dispatch($event->notification->discord_message_id);
    }
}
