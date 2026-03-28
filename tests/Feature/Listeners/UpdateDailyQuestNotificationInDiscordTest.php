<?php

namespace Tests\Feature\Listeners;

use App\Events\DailyQuestNotificationUpdated;
use App\Jobs\UpdateDailyQuestNotificationInDiscord as UpdateJob;
use App\Listeners\UpdateDailyQuestNotificationInDiscord;
use App\Models\TBC\DailyQuestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateDailyQuestNotificationInDiscordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_update_job_when_discord_message_id_exists(): void
    {
        Bus::fake();

        $notification = DailyQuestNotification::factory()->create();
        $event = new DailyQuestNotificationUpdated($notification);

        $listener = new UpdateDailyQuestNotificationInDiscord;
        $listener->handle($event);

        Bus::assertDispatched(UpdateJob::class, function (UpdateJob $job) use ($notification) {
            return $job->notification->is($notification);
        });
    }

    #[Test]
    public function it_does_not_dispatch_when_discord_message_id_is_null(): void
    {
        Bus::fake();

        $notification = DailyQuestNotification::factory()->withoutDiscordMessage()->create();
        $event = new DailyQuestNotificationUpdated($notification);

        $listener = new UpdateDailyQuestNotificationInDiscord;
        $listener->handle($event);

        Bus::assertNotDispatched(UpdateJob::class);
    }
}
