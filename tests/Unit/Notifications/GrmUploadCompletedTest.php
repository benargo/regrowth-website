<?php

namespace Tests\Unit\Notifications;

use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadCompleted;
use NotificationChannels\Discord\DiscordChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrmUploadCompletedTest extends TestCase
{
    #[Test]
    public function it_uses_discord_channel(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 5);

        $this->assertSame([DiscordChannel::class], $notification->via(new DiscordNotifiable('officer')));
    }

    #[Test]
    public function it_builds_embed_with_processed_count(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertSame('GRM Upload Processing Completed', $message->embed['title']);
        $this->assertSame(5763719, $message->embed['color']);
        $this->assertCount(1, $message->embed['fields']);
        $this->assertSame('Processed', $message->embed['fields'][0]['name']);
        $this->assertSame('10', $message->embed['fields'][0]['value']);
    }

    #[Test]
    public function it_includes_skipped_field_when_skipped_count_greater_than_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 3);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertCount(2, $message->embed['fields']);
        $this->assertSame('Skipped (too low level)', $message->embed['fields'][1]['name']);
        $this->assertSame('3', $message->embed['fields'][1]['value']);
    }

    #[Test]
    public function it_excludes_skipped_field_when_skipped_count_is_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 0);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertCount(1, $message->embed['fields']);
    }

    #[Test]
    public function it_includes_warning_field_when_warning_count_greater_than_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, warningCount: 2);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertCount(2, $message->embed['fields']);
        $this->assertSame('Skipped (API issues)', $message->embed['fields'][1]['name']);
        $this->assertSame('2', $message->embed['fields'][1]['value']);
    }

    #[Test]
    public function it_excludes_warning_field_when_warning_count_is_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, warningCount: 0);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertCount(1, $message->embed['fields']);
    }

    #[Test]
    public function it_includes_both_skipped_and_warning_fields(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 2, warningCount: 1);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertCount(3, $message->embed['fields']);
    }

    #[Test]
    public function it_includes_image_and_timestamp(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 5);
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertArrayHasKey('image', $message->embed);
        $this->assertArrayHasKey('timestamp', $message->embed);
    }

    #[Test]
    public function it_returns_grm_upload_tags(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 5);

        $this->assertSame(['grm-upload'], $notification->tags());
    }
}
