<?php

namespace Tests\Feature\Notifications;

use App\Notifications\LevelCapAchieved;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;
use Tests\TestCase;

class LevelCapAchievedTest extends TestCase
{
    public function test_it_routes_via_discord_channel(): void
    {
        $notification = new LevelCapAchieved(['TestChar']);
        $channels = $notification->via(new \stdClass);

        $this->assertEquals([DiscordChannel::class], $channels);
    }

    public function test_it_formats_singular_name_correctly(): void
    {
        $notification = new LevelCapAchieved(['Arthas']);
        $message = $notification->toDiscord(new \stdClass);

        $this->assertInstanceOf(DiscordMessage::class, $message);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertEquals('Level 70 Achieved!', $embed['title']);
        $this->assertStringContainsString('Congratulations to **Arthas**', $embed['description']);
        $this->assertStringContainsString('reaching level 70', $embed['description']);
    }

    public function test_it_formats_two_names_correctly(): void
    {
        $notification = new LevelCapAchieved(['Arthas', 'Jaina']);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas** and **Jaina**', $embed['description']);
    }

    public function test_it_formats_three_or_more_names_with_oxford_comma(): void
    {
        $notification = new LevelCapAchieved(['Arthas', 'Jaina', 'Thrall']);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas**, **Jaina**, and **Thrall**', $embed['description']);
    }

    public function test_it_formats_four_names_with_oxford_comma(): void
    {
        $notification = new LevelCapAchieved(['Arthas', 'Jaina', 'Thrall', 'Sylvanas']);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas**, **Jaina**, **Thrall**, and **Sylvanas**', $embed['description']);
    }

    public function test_it_includes_green_color_in_embed(): void
    {
        $notification = new LevelCapAchieved(['TestChar']);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertEquals(5763719, $embed['color']);
    }

    public function test_it_includes_timestamp_in_embed(): void
    {
        $notification = new LevelCapAchieved(['TestChar']);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayHasKey('timestamp', $embed);
    }

    /**
     * Extract the embed array from a DiscordMessage.
     *
     * @return array<string, mixed>
     */
    protected function getEmbedFromMessage(DiscordMessage $message): array
    {
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('embed');
        $property->setAccessible(true);

        return $property->getValue($message);
    }
}
