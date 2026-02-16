<?php

namespace App\Listeners;

use App\Events\DailyQuestNotificationCreated;
use App\Jobs\SendDailyQuestNotificationToDiscord as SendJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDailyQuestNotificationToDiscord implements ShouldQueue
{
    public function handle(DailyQuestNotificationCreated $event): void
    {
        SendJob::dispatch($event->notification);
    }
}
