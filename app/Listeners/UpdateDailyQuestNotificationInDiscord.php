<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationUpdated;
use App\Jobs\UpdateDailyQuestNotificationInDiscord as UpdateJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateDailyQuestNotificationInDiscord implements ShouldQueue
{
    public function handle(DailyQuestNotificationUpdated $event): void
    {
        if (! $event->notification->discord_message_id) {
            return;
        }

        UpdateJob::dispatch($event->notification);
    }
}
