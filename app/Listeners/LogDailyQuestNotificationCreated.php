<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationCreated;
use Illuminate\Support\Facades\Log;

class LogDailyQuestNotificationCreated
{
    public function handle(DailyQuestNotificationCreated $event): void
    {
        $notification = $event->notification;
        $user = $notification->sentBy;
        $date = $notification->date->format('Y-m-d');

        Log::channel('daily-quests')->info(
            "{$user->display_name} posted daily quests for {$date}."
        );
    }
}
