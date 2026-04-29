<?php

namespace Tests\Unit\Notifications;

use App\Notifications\GrmUploadCompleted;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as ChannelResource;
use App\Services\Discord\Resources\Embed;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrmUploadCompletedTest extends TestCase
{
    private function makeNotifiable(): NotifiableChannel
    {
        $channel = ChannelResource::from(['id' => '1407688195386114119', 'type' => 0]);

        return new NotifiableChannel($channel);
    }

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 5);

        $this->assertSame(DiscordDriver::class, $notification->via($this->makeNotifiable()));
    }

    #[Test]
    public function it_builds_embed_with_processed_count(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10);
        $payload = $notification->toMessage();

        $embed = $payload->embeds[0];
        $this->assertInstanceOf(Embed::class, $embed);
        $this->assertSame('GRM Upload Processing Completed', $embed->title);
        $this->assertSame(5763719, $embed->color);
        $this->assertCount(1, $embed->fields);
        $this->assertSame('Processed', $embed->fields[0]->name);
        $this->assertSame('10', $embed->fields[0]->value);
    }

    #[Test]
    public function it_includes_skipped_field_when_skipped_count_greater_than_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 3);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(2, $embed->fields);
        $this->assertSame('Skipped (too low level)', $embed->fields[1]->name);
        $this->assertSame('3', $embed->fields[1]->value);
    }

    #[Test]
    public function it_excludes_skipped_field_when_skipped_count_is_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 0);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(1, $embed->fields);
    }

    #[Test]
    public function it_includes_warning_field_when_warning_count_greater_than_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, warningCount: 2);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(2, $embed->fields);
        $this->assertSame('Skipped (API issues)', $embed->fields[1]->name);
        $this->assertSame('2', $embed->fields[1]->value);
    }

    #[Test]
    public function it_excludes_warning_field_when_warning_count_is_zero(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, warningCount: 0);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(1, $embed->fields);
    }

    #[Test]
    public function it_includes_both_skipped_and_warning_fields(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 10, skippedCount: 2, warningCount: 1);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(3, $embed->fields);
    }

    #[Test]
    public function it_includes_image_and_timestamp(): void
    {
        $notification = new GrmUploadCompleted(processedCount: 5);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertNotNull($embed->image);
        $this->assertNotNull($embed->timestamp);
    }

    #[Test]
    public function it_returns_null_for_updates(): void
    {
        $this->assertNull((new GrmUploadCompleted(processedCount: 5))->updates());
    }

    #[Test]
    public function it_returns_null_for_sender(): void
    {
        $this->assertNull((new GrmUploadCompleted(processedCount: 5))->sender());
    }

    #[Test]
    public function it_returns_empty_array_for_relationships(): void
    {
        $this->assertEmpty((new GrmUploadCompleted(processedCount: 5))->relationships());
    }
}
