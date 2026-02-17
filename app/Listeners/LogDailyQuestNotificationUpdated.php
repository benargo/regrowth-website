<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationUpdated;
use Illuminate\Support\Facades\Log;

class LogDailyQuestNotificationUpdated
{
    public function handle(DailyQuestNotificationUpdated $event): void
    {
        $notification = $event->notification;
        $user = $notification->updatedBy;
        $date = $notification->date->format('Y-m-d');

        Log::channel('daily-quests')->info(
            "{$user->display_name} updated daily quests for {$date}."
        );
    }
}
