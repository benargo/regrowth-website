<?php

namespace Tests\Feature\Listeners;

use App\Events\GrmUploadProcessed;
use App\Jobs\ProcessGrmUpload;
use App\Jobs\SendGrmUploadNotification;
use App\Listeners\HandleGrmUpload;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleGrmUploadTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Listener Contract Tests
    // ==========================================

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new HandleGrmUpload);
    }

    // ==========================================
    // Happy Path
    // ==========================================

    #[Test]
    public function it_dispatches_send_grm_upload_notification(): void
    {
        Bus::fake();

        $listener = new HandleGrmUpload;
        $listener->handle(new GrmUploadProcessed(5, 1, 0, 0, []));

        Bus::assertDispatched(SendGrmUploadNotification::class);
    }

    #[Test]
    public function it_passes_all_event_data_to_the_notification_job(): void
    {
        Bus::fake();

        $listener = new HandleGrmUpload;
        $listener->handle(new GrmUploadProcessed(10, 2, 1, 3, ['Error A', 'Error B']));

        Bus::assertDispatched(SendGrmUploadNotification::class, function ($job) {
            return $job->processedCount === 10
                && $job->skippedCount === 2
                && $job->warningCount === 1
                && $job->errorCount === 3
                && $job->errors === ['Error A', 'Error B'];
        });
    }

    #[Test]
    public function it_dispatches_exactly_one_notification_job(): void
    {
        Bus::fake();

        $listener = new HandleGrmUpload;
        $listener->handle(new GrmUploadProcessed(5, 0, 0, 0, []));

        Bus::assertDispatchedTimes(SendGrmUploadNotification::class, 1);
    }

    // ==========================================
    // failed() Handler Tests
    // ==========================================

    #[Test]
    public function failed_updates_cache_with_failed_status(): void
    {
        Notification::fake();

        config(['services.discord.channels.officer' => '1407688195386114119']);

        $event = new GrmUploadProcessed(10, 2, 1, 0, []);
        $exception = new \RuntimeException('Serialization failed');

        $listener = new HandleGrmUpload;
        $listener->failed($event, $exception);

        $progress = Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY);
        $this->assertEquals('failed', $progress['status']);
        $this->assertEquals(2, $progress['step']);
        $this->assertEquals(3, $progress['total']);
        $this->assertStringContainsString('Serialization failed', $progress['message']);
        $this->assertEquals(10, $progress['processedCount']);
        $this->assertEquals(2, $progress['skippedCount']);
        $this->assertEquals(1, $progress['warningCount']);
        $this->assertEquals(0, $progress['errorCount']);
    }

    #[Test]
    public function failed_sends_grm_upload_failed_discord_notification(): void
    {
        Notification::fake();

        config(['services.discord.channels.officer' => '1407688195386114119']);

        $event = new GrmUploadProcessed(10, 2, 1, 0, []);
        $exception = new \RuntimeException('Something broke');

        $listener = new HandleGrmUpload;
        $listener->failed($event, $exception);

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }
}
