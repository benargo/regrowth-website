<?php

namespace Tests\Unit\Notifications;

use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Models\DiscordChannel;
use App\Notifications\Discord\DiscordNotificationChannel;
use App\Notifications\GuildRosterManagerUploadCompleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class GuildRosterManagerUploadCompletedTest extends TestCase
{
    private function makeNotifiable(): DiscordChannel
    {
        $channel = DiscordChannel::factory()->make(['id' => '1407688195386114119']);

        return $channel;
    }

    // ==================== delivery ====================

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 5);

        $this->assertContains(DiscordNotificationChannel::class, $notification->via($this->makeNotifiable()));
    }

    // ==================== embed content ====================

    #[Test]
    public function it_builds_embed_with_processed_count(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10);
        $payload = $notification->toMessage();

        $embed = $payload->embeds[0];
        $this->assertInstanceOf(EmbedData::class, $embed);
        $this->assertSame('GRM Upload Processing Completed', $embed->title);
        $this->assertSame(5763719, $embed->color);
        $this->assertCount(1, $embed->fields);
        $this->assertSame('Processed', $embed->fields[0]->name);
        $this->assertSame('10', $embed->fields[0]->value);
    }

    #[Test]
    public function it_includes_skipped_field_when_skipped_count_greater_than_zero(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10, skippedCount: 3);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(2, $embed->fields);
        $this->assertSame('Skipped (too low level)', $embed->fields[1]->name);
        $this->assertSame('3', $embed->fields[1]->value);
    }

    #[Test]
    public function it_excludes_skipped_field_when_skipped_count_is_zero(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10, skippedCount: 0);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(1, $embed->fields);
    }

    #[Test]
    public function it_includes_warning_field_when_warning_count_greater_than_zero(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10, warningCount: 2);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(2, $embed->fields);
        $this->assertSame('Skipped (API issues)', $embed->fields[1]->name);
        $this->assertSame('2', $embed->fields[1]->value);
    }

    #[Test]
    public function it_excludes_warning_field_when_warning_count_is_zero(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10, warningCount: 0);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(1, $embed->fields);
    }

    #[Test]
    public function it_includes_both_skipped_and_warning_fields(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 10, skippedCount: 2, warningCount: 1);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertCount(3, $embed->fields);
    }

    #[Test]
    public function it_includes_image_and_timestamp(): void
    {
        $notification = new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 5);
        $embed = $notification->toMessage()->embeds[0];

        $this->assertNotNull($embed->image);
        $this->assertNotNull($embed->timestamp);
    }

    // ==================== notification metadata ====================

    #[Test]
    public function it_returns_null_for_sender(): void
    {
        $this->assertNull((new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 5))->sender());
    }

    #[Test]
    public function it_returns_empty_array_for_related_models(): void
    {
        $this->assertEmpty((new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc', processedCount: 5))->mapRelatedModels());
    }

    // ==================== embed link ====================

    #[Test]
    public function it_links_to_the_export_page_for_the_uploads_game_version(): void
    {
        $embed = (new GuildRosterManagerUploadCompleted(gameVersionSlug: 'tbc-anniversary', processedCount: 5))->toMessage()->embeds[0];

        $this->assertSame(route('addons.loot-tool.export', ['game_version' => 'tbc-anniversary']), $embed->url);
        $this->assertStringContainsString('game_version=tbc-anniversary', $embed->url);
    }
}
