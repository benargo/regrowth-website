<?php

namespace Tests\Feature;

use App\Models\TBC\DailyQuestNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestsAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_logs_when_daily_quests_are_created(): void
    {
        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'posted daily quests for');
            });

        DailyQuestNotification::factory()->create();
    }

    #[Test]
    public function it_logs_the_correct_user_on_creation(): void
    {
        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->once()
            ->andReturnSelf();

        $user = User::factory()->create(['nickname' => 'TestOfficer']);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_starts_with($message, 'TestOfficer posted daily quests for');
            });

        DailyQuestNotification::factory()
            ->create(['sent_by_user_id' => $user->id]);
    }

    #[Test]
    public function it_logs_when_daily_quests_are_updated(): void
    {
        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'posted daily quests for');
            });

        $updater = User::factory()->create(['nickname' => 'UpdaterNick']);
        $notification = DailyQuestNotification::factory()->create();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_starts_with($message, 'UpdaterNick updated daily quests for');
            });

        $notification->update(['updated_by_user_id' => $updater->id]);
    }

    #[Test]
    public function it_logs_authenticated_user_on_delete(): void
    {
        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'posted daily quests for');
            });

        $user = User::factory()->create(['nickname' => 'DeleterNick']);
        $notification = DailyQuestNotification::factory()->create();

        $this->actingAs($user);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_starts_with($message, 'DeleterNick deleted daily quests for');
            });

        $notification->delete();
    }

    #[Test]
    public function it_logs_system_on_delete_without_authenticated_user(): void
    {
        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'posted daily quests for');
            });

        $notification = DailyQuestNotification::factory()->create();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_starts_with($message, 'System deleted daily quests for');
            });

        $notification->delete();
    }

    #[Test]
    public function it_includes_the_correct_date_in_log_messages(): void
    {
        $date = now('Europe/Paris')->startOfDay()->addHours(3);

        Log::shouldReceive('channel')
            ->with('daily-quests')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) use ($date) {
                return str_contains($message, $date->format('Y-m-d'));
            });

        DailyQuestNotification::factory()
            ->forDate($date)
            ->create();
    }
}
