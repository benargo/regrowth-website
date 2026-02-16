<?php

namespace App\Jobs;

use App\Models\TBC\DailyQuestNotification;
use App\Notifications\DailyQuestsSet;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateDailyQuestNotificationInDiscord implements ShouldQueue
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

        $discordMessageService->updateMessage(
            $channelId,
            $this->notification->discord_message_id,
            $payload
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to update daily quest notification in Discord', [
            'notification_id' => $this->notification->id,
            'discord_message_id' => $this->notification->discord_message_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
