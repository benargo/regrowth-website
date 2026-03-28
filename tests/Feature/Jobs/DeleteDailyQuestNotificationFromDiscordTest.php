<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteDailyQuestNotificationFromDiscord;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteDailyQuestNotificationFromDiscordTest extends TestCase
{
    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $job = new DeleteDailyQuestNotificationFromDiscord('123456');

        $this->assertSame(2, $job->tries);
        $this->assertSame(30, $job->backoff);
    }

    #[Test]
    public function it_calls_delete_message_on_discord_service(): void
    {
        config(['services.discord.channels.daily_quests' => '999888777']);

        $discordMessageService = $this->mock(DiscordMessageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteMessage')
                ->once()
                ->with('999888777', '123456');
        });

        $job = new DeleteDailyQuestNotificationFromDiscord('123456');
        $job->handle($discordMessageService);
    }

    #[Test]
    public function it_logs_warning_on_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to delete daily quest notification from Discord', [
                'discord_message_id' => '123456',
                'error' => 'Something went wrong',
            ]);

        $job = new DeleteDailyQuestNotificationFromDiscord('123456');
        $job->failed(new \Exception('Something went wrong'));
    }
}
