<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendDailyQuestNotificationToDiscord;
use App\Models\TBC\DailyQuestNotification;
use App\Services\Discord\DiscordMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendDailyQuestNotificationToDiscordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $notification = DailyQuestNotification::factory()->create();
        $job = new SendDailyQuestNotificationToDiscord($notification);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    #[Test]
    public function it_creates_message_and_saves_discord_message_id(): void
    {
        config(['services.discord.channels.daily_quests' => '999888777']);

        $notification = DailyQuestNotification::factory()->withoutDiscordMessage()->create();

        $discordMessageService = $this->mock(DiscordMessageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMessage')
                ->once()
                ->with('999888777', \Mockery::type('array'))
                ->andReturn('new-discord-msg-id');
        });

        $job = new SendDailyQuestNotificationToDiscord($notification);
        $job->handle($discordMessageService);

        $notification->refresh();
        $this->assertSame('new-discord-msg-id', $notification->discord_message_id);
    }

    #[Test]
    public function it_does_not_update_model_when_message_id_is_null(): void
    {
        config(['services.discord.channels.daily_quests' => '999888777']);

        $notification = DailyQuestNotification::factory()->withoutDiscordMessage()->create();

        $discordMessageService = $this->mock(DiscordMessageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMessage')
                ->once()
                ->andReturn(null);
        });

        $job = new SendDailyQuestNotificationToDiscord($notification);
        $job->handle($discordMessageService);

        $notification->refresh();
        $this->assertNull($notification->discord_message_id);
    }

    #[Test]
    public function it_logs_error_on_failure(): void
    {
        $notification = DailyQuestNotification::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send daily quest notification to Discord', \Mockery::on(function (array $context) use ($notification) {
                return $context['notification_id'] === $notification->id
                    && $context['error'] === 'Something went wrong'
                    && isset($context['trace']);
            }));

        $job = new SendDailyQuestNotificationToDiscord($notification);
        $job->failed(new \Exception('Something went wrong'));
    }
}
