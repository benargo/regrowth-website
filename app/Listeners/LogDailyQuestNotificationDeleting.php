<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationDeleting;
use Illuminate\Support\Facades\Log;

class LogDailyQuestNotificationDeleting
{
    public function handle(DailyQuestNotificationDeleting $event): void
    {
        $notification = $event->notification;
        $user = auth()->user();
        $actor = $user ? $user->display_name : 'System';
        $date = $notification->date->format('Y-m-d');

        Log::channel('daily-quests')->info(
            "{$actor} deleted daily quests for {$date}."
        );
    }
}
