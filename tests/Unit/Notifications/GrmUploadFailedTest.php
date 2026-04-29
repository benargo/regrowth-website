<?php

namespace Tests\Unit\Notifications;

use App\Notifications\GrmUploadFailed;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as ChannelResource;
use App\Services\Discord\Resources\Embed;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrmUploadFailedTest extends TestCase
{
    private function makeNotifiable(): NotifiableChannel
    {
        $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);

        return new NotifiableChannel($channel);
    }

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $notification = new GrmUploadFailed(processedCount: 3, errorCount: 2);

        $this->assertSame(DiscordDriver::class, $notification->via($this->makeNotifiable()));
    }

    #[Test]
    public function it_builds_embed_for_exception_path(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 0,
            errorCount: 1,
            exceptionMessage: 'Connection timed out',
        );
        $embed = $notification->toMessage()->embeds[0];

        $this->assertInstanceOf(Embed::class, $embed);
        $this->assertSame('GRM Upload Processing Failed', $embed->title);
        $this->assertSame(15158332, $embed->color);
        $this->assertStringContainsString('Connection timed out', $embed->description);
        $this->assertStringContainsString('failed completely', $embed->description);
        $this->assertNull($embed->fields);
    }

    #[Test]
    public function it_builds_embed_for_errors_only_path(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 8,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );
        $embed = $notification->toMessage()->embeds[0];

        $this->assertSame('GRM Upload Processing Completed with Errors', $embed->title);
        $this->assertCount(2, $embed->fields);
        $this->assertSame('Processed', $embed->fields[0]->name);
        $this->assertSame('8', $embed->fields[0]->value);
        $this->assertSame('Errors', $embed->fields[1]->name);
        $this->assertSame('2', $embed->fields[1]->value);
        $this->assertNotNull($embed->image);
    }

    #[Test]
    public function it_includes_error_list_in_description(): void
    {
        $notification = new GrmUploadFailed(
            processedCount: 5,
            errorCount: 2,
            errors: ['CharA: API error', 'CharB: not found'],
        );
        $embed = $notification->toMessage()->embeds[0];

        $this->assertStringContainsString('CharA: API error', $embed->description);
        $this->assertStringContainsString('CharB: not found', $embed->description);
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
        $embed = $notification->toMessage()->embeds[0];

        $this->assertStringContainsString('Error 10', $embed->description);
        $this->assertStringNotContainsString('Error 11', $embed->description);
        $this->assertStringContainsString('and 5 more errors', $embed->description);
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
        $embed = $notification->toMessage()->embeds[0];

        $this->assertStringContainsString('Error 10', $embed->description);
        $this->assertStringNotContainsString('more errors', $embed->description);
    }

    #[Test]
    public function it_returns_null_for_updates(): void
    {
        $this->assertNull((new GrmUploadFailed(processedCount: 0, errorCount: 1))->updates());
    }

    #[Test]
    public function it_returns_null_for_sender(): void
    {
        $this->assertNull((new GrmUploadFailed(processedCount: 0, errorCount: 1))->sender());
    }

    #[Test]
    public function it_returns_empty_array_for_relationships(): void
    {
        $this->assertEmpty((new GrmUploadFailed(processedCount: 0, errorCount: 1))->relationships());
    }
}
