<?php

namespace Tests\Feature\Console\Commands;

use App\Events\DailyQuestNotificationDeleting;
use App\Models\TBC\DailyQuestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResetDailyQuestsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_soft_deletes_all_daily_quest_notifications(): void
    {
        Queue::fake();

        $notifications = DailyQuestNotification::factory()
            ->count(3)
            ->sequence(
                ['date' => now('Europe/Paris')->subDays(2)->startOfDay()->addHours(3)],
                ['date' => now('Europe/Paris')->subDays(1)->startOfDay()->addHours(3)],
                ['date' => now('Europe/Paris')->startOfDay()->addHours(3)],
            )
            ->create();

        $this->artisan('app:reset-daily-quests')
            ->expectsOutput('Resetting daily quests...')
            ->assertSuccessful();

        foreach ($notifications as $notification) {
            $this->assertSoftDeleted($notification);
        }
    }

    #[Test]
    public function it_skips_when_no_notifications_exist(): void
    {
        $this->artisan('app:reset-daily-quests')
            ->expectsOutput('Resetting daily quests...')
            ->expectsOutput('No daily quest notification messages found. Task skipped.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_dispatches_deleting_event_for_each_notification(): void
    {
        Event::fake([DailyQuestNotificationDeleting::class]);

        DailyQuestNotification::factory()->create();

        $this->artisan('app:reset-daily-quests')
            ->assertSuccessful();

        Event::assertDispatched(DailyQuestNotificationDeleting::class);
    }
}
