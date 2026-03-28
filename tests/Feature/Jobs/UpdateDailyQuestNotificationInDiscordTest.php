<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UpdateDailyQuestNotificationInDiscord;
use App\Models\TBC\DailyQuestNotification;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateDailyQuestNotificationInDiscordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $notification = DailyQuestNotification::factory()->create();

        $job = new UpdateDailyQuestNotificationInDiscord($notification);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    #[Test]
    public function it_calls_update_message_on_discord_service(): void
    {
        config(['services.discord.channels.daily_quests' => '999888777']);

        $notification = DailyQuestNotification::factory()->create();

        $discordMessageService = $this->mock(DiscordMessageService::class, function (MockInterface $mock) use ($notification) {
            $mock->shouldReceive('updateMessage')
                ->once()
                ->with(
                    '999888777',
                    $notification->discord_message_id,
                    \Mockery::type('array')
                );
        });

        $job = new UpdateDailyQuestNotificationInDiscord($notification);
        $job->handle($discordMessageService);
    }

    #[Test]
    public function it_logs_error_on_failure(): void
    {
        $notification = DailyQuestNotification::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to update daily quest notification in Discord', \Mockery::on(function (array $context) use ($notification) {
                return $context['notification_id'] === $notification->id
                    && $context['discord_message_id'] === $notification->discord_message_id
                    && $context['error'] === 'Something went wrong'
                    && isset($context['trace']);
            }));

        $job = new UpdateDailyQuestNotificationInDiscord($notification);
        $job->failed(new \Exception('Something went wrong'));
    }
}
