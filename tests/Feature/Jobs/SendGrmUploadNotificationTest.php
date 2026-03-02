<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessGrmUpload;
use App\Jobs\SendGrmUploadNotification;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendGrmUploadNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);
    }

    public function test_it_sends_grm_upload_completed_notification_when_no_errors(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 5,
            skippedCount: 1,
            warningCount: 0,
            errorCount: 0,
            errors: [],
        );

        $job->handle();

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadCompleted::class
        );
    }

    public function test_it_sends_grm_upload_failed_notification_when_errors_exist(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 3,
            skippedCount: 0,
            warningCount: 0,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );

        $job->handle();

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }

    public function test_it_sends_failed_notification_even_when_processed_count_is_zero(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 0,
            skippedCount: 0,
            warningCount: 0,
            errorCount: 1,
            errors: ['SomeChar: API error'],
        );

        $job->handle();

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }

    public function test_it_sends_completed_notification_with_skipped_and_warning_counts(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 10,
            skippedCount: 3,
            warningCount: 2,
            errorCount: 0,
            errors: [],
        );

        $job->handle();

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadCompleted::class
        );
    }

    public function test_it_does_not_send_completed_notification_when_errors_exist(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 5,
            skippedCount: 0,
            warningCount: 0,
            errorCount: 1,
            errors: ['SomeChar: error'],
        );

        $job->handle();

        Notification::assertNotSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadCompleted::class
        );
    }

    public function test_it_does_not_send_failed_notification_when_no_errors(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 5,
            skippedCount: 0,
            warningCount: 0,
            errorCount: 0,
            errors: [],
        );

        $job->handle();

        Notification::assertNotSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }

    public function test_it_updates_cache_to_completed_when_no_errors(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 5,
            skippedCount: 1,
            warningCount: 0,
            errorCount: 0,
            errors: [],
        );

        $job->handle();

        $progress = Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY);
        $this->assertEquals('completed', $progress['status']);
        $this->assertEquals(3, $progress['step']);
        $this->assertEquals(5, $progress['processedCount']);
    }

    public function test_it_updates_cache_to_failed_when_errors_exist(): void
    {
        $job = new SendGrmUploadNotification(
            processedCount: 3,
            skippedCount: 0,
            warningCount: 0,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );

        $job->handle();

        $progress = Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY);
        $this->assertEquals('failed', $progress['status']);
        $this->assertEquals(3, $progress['step']);
        $this->assertEquals(2, $progress['errorCount']);
        $this->assertCount(2, $progress['errors']);
    }
}
