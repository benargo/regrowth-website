<?php

namespace App\Jobs;

use App\Models\TBC\DailyQuestNotification;
use App\Notifications\DailyQuestsSet;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDailyQuestNotificationToDiscord implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public DailyQuestNotification $notification
    ) {}

    public function handle(DiscordMessageService $discordMessageService): void
    {
        $channelId = config('services.discord.channels.daily_quests');
        $messageBuilder = new DailyQuestsSet($this->notification);
        $payload = $messageBuilder->getPayload();

        $messageId = $discordMessageService->createMessage($channelId, $payload);

        if ($messageId) {
            $this->notification->withoutEvents(function () use ($messageId) {
                $this->notification->update(['discord_message_id' => $messageId]);
            });
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send daily quest notification to Discord', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
