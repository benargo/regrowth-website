<?php

namespace App\Events;

use App\Models\TBC\DailyQuestNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyQuestNotificationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DailyQuestNotification $notification
    ) {}
}
