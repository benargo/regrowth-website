<?php

namespace Tests\Feature\Listeners;

use App\Events\DailyQuestNotificationDeleting;
use App\Jobs\DeleteDailyQuestNotificationFromDiscord as DeleteJob;
use App\Listeners\DeleteDailyQuestNotificationFromDiscord;
use App\Models\TBC\DailyQuestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteDailyQuestNotificationFromDiscordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_delete_job_when_discord_message_id_exists(): void
    {
        Bus::fake();

        $notification = DailyQuestNotification::factory()->create();
        $event = new DailyQuestNotificationDeleting($notification);

        $listener = new DeleteDailyQuestNotificationFromDiscord;
        $listener->handle($event);

        Bus::assertDispatched(DeleteJob::class, function (DeleteJob $job) use ($notification) {
            return $job->discordMessageId === $notification->discord_message_id;
        });
    }

    #[Test]
    public function it_does_not_dispatch_when_discord_message_id_is_null(): void
    {
        Bus::fake();

        $notification = DailyQuestNotification::factory()->withoutDiscordMessage()->create();
        $event = new DailyQuestNotificationDeleting($notification);

        $listener = new DeleteDailyQuestNotificationFromDiscord;
        $listener->handle($event);

        Bus::assertNotDispatched(DeleteJob::class);
    }
}
