<?php

namespace Tests\Unit\Notifications;

use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
use NotificationChannels\Discord\DiscordChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrmUploadFailedTest extends TestCase
{
    #[Test]
    public function it_uses_discord_channel(): void
    {
        $notification = new GrmUploadFailed(processedCount: 3, errorCount: 2);

        $this->assertSame([DiscordChannel::class], $notification->via(new DiscordNotifiable('officer')));
    }

    #[Test]
    public function it_builds_embed_for_exception_path(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 0,
            errorCount: 1,
            exceptionMessage: 'Connection timed out',
        );
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertSame('GRM Upload Processing Failed', $message->embed['title']);
        $this->assertSame(15158332, $message->embed['color']);
        $this->assertStringContainsString('Connection timed out', $message->embed['description']);
        $this->assertStringContainsString('failed completely', $message->embed['description']);
        $this->assertArrayNotHasKey('fields', $message->embed);
    }

    #[Test]
    public function it_builds_embed_for_errors_only_path(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 8,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertSame('GRM Upload Processing Completed with Errors', $message->embed['title']);
        $this->assertArrayHasKey('fields', $message->embed);
        $this->assertCount(2, $message->embed['fields']);
        $this->assertSame('Processed', $message->embed['fields'][0]['name']);
        $this->assertSame('8', $message->embed['fields'][0]['value']);
        $this->assertSame('Errors', $message->embed['fields'][1]['name']);
        $this->assertSame('2', $message->embed['fields'][1]['value']);
        $this->assertArrayHasKey('image', $message->embed);
    }

    #[Test]
    public function it_includes_error_list_in_description(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 5,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertStringContainsString('CharA: API error', $message->embed['description']);
        $this->assertStringContainsString('CharB: not found', $message->embed['description']);
    }

    #[Test]
    public function it_truncates_errors_at_ten(): void
    {
        $errors = array_map(fn ($i) => "Error {$i}", range(1, 15));

        $notification = new GrmUploadFailed(
            processedCount: 5,
            errorCount: 15,
            errors: $errors,
        );
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertStringContainsString('Error 10', $message->embed['description']);
        $this->assertStringNotContainsString('Error 11', $message->embed['description']);
        $this->assertStringContainsString('and 5 more errors', $message->embed['description']);
    }

    #[Test]
    public function it_shows_all_errors_when_ten_or_fewer(): void
    {
        $errors = array_map(fn ($i) => "Error {$i}", range(1, 10));

        $notification = new GrmUploadFailed(
            processedCount: 5,
            errorCount: 10,
            errors: $errors,
        );
        $message = $notification->toDiscord(new DiscordNotifiable('officer'));

        $this->assertStringContainsString('Error 10', $message->embed['description']);
        $this->assertStringNotContainsString('more errors', $message->embed['description']);
    }

    #[Test]
    public function it_returns_grm_upload_tags(): void
    {
        $notification = new GrmUploadFailed(processedCount: 0, errorCount: 1);

        $this->assertSame(['grm-upload'], $notification->tags());
    }
}
