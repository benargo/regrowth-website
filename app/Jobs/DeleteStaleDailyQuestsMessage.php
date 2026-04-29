<?php

namespace App\Jobs;

use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Discord;
use App\Services\Discord\Stubs\MessageStub;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteStaleDailyQuestsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function handle(Discord $discord): void
    {
        $stale = DiscordNotification::where('type', DailyQuestsMessage::class)
            ->where('created_at', '<', Carbon::yesterday()->setTime(4, 0, 0))
            ->get();

        foreach ($stale as $notification) {
            $discord->deleteMessage(new MessageStub(
                id: $notification->message_id,
                channel_id: $notification->channel_id,
            ));

            $notification->delete();
        }
    }
}
