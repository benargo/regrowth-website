<?php

namespace App\Jobs;

use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Stubs\MessageStub;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        try {
            foreach ($stale as $notification) {
                $discord->deleteMessage(new MessageStub(
                    id: $notification->message_id,
                    channel_id: $notification->channel_id,
                ));

                $notification->delete();
            }
        } catch (RateLimitedException $e) {
            Log::warning('DeleteStaleDailyQuestsMessage: Discord rate limited, releasing job.', [
                'endpoint' => $e->endpoint,
                'retry_after' => $e->retryAfter,
                'scope' => $e->scope,
            ]);
            $this->release($e->retryAfter);

            return;
        }
    }
}
