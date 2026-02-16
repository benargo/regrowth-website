<?php

namespace App\Jobs;

use App\Services\Discord\DiscordMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeleteDailyQuestNotificationFromDiscord implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public string $discordMessageId
    ) {}

    public function handle(DiscordMessageService $discordMessageService): void
    {
        $channelId = config('services.discord.channels.daily_quests');

        $discordMessageService->deleteMessage($channelId, $this->discordMessageId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Failed to delete daily quest notification from Discord', [
            'discord_message_id' => $this->discordMessageId,
            'error' => $exception->getMessage(),
        ]);
    }
}
