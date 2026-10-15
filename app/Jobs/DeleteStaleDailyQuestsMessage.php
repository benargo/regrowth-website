<?php

namespace App\Jobs;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Requests\Messages\DeleteMessageRequest;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteStaleDailyQuestsMessage implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 2;

    public int $backoff = 30;

    /**
     * Rate-limit releases keep the job alive until this deadline.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ReleaseWhenDiscordRateLimited];
    }

    public function handle(DiscordConnector $discord): void
    {
        $stale = DiscordNotification::where('type', DailyQuestsMessage::class)
            ->where('created_at', '<', today()->subDay()->setTime(4, 0, 0))
            ->get();

        foreach ($stale as $notification) {
            try {
                $discord->send(new DeleteMessageRequest($notification->channel_id, $notification->message_id));
            } catch (MessageNotFoundException) {
                // The Discord message is already gone: delete the record only.
            }

            $notification->delete();
        }
    }
}
