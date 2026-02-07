<?php

namespace Tests\Feature\Notifications;

use App\Models\Character;
use App\Notifications\LevelCapAchieved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;
use Tests\TestCase;

class LevelCapAchievedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_via_discord_channel(): void
    {
        $character = Character::factory()->create(['name' => 'TestChar', 'reached_level_cap_at' => now()]);
        $notification = new LevelCapAchieved([$character]);
        $channels = $notification->via(new \stdClass);

        $this->assertEquals([DiscordChannel::class], $channels);
    }

    public function test_it_formats_singular_name_correctly(): void
    {
        $character = Character::factory()->create(['name' => 'Arthas', 'reached_level_cap_at' => now()]);
        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $this->assertInstanceOf(DiscordMessage::class, $message);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertEquals('Level 70 Achieved!', $embed['title']);
        $this->assertStringContainsString('Congratulations to **Arthas**', $embed['description']);
        $this->assertStringContainsString('reaching level 70', $embed['description']);
    }

    public function test_it_formats_two_names_correctly(): void
    {
        $characters = Character::factory()->count(2)->sequence(
            ['name' => 'Arthas', 'reached_level_cap_at' => now()],
            ['name' => 'Jaina', 'reached_level_cap_at' => now()],
        )->create();

        $notification = new LevelCapAchieved($characters);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas** and **Jaina**', $embed['description']);
    }

    public function test_it_formats_three_or_more_names_with_oxford_comma(): void
    {
        $characters = Character::factory()->count(3)->sequence(
            ['name' => 'Arthas', 'reached_level_cap_at' => now()],
            ['name' => 'Jaina', 'reached_level_cap_at' => now()],
            ['name' => 'Thrall', 'reached_level_cap_at' => now()],
        )->create();

        $notification = new LevelCapAchieved($characters);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas**, **Jaina**, and **Thrall**', $embed['description']);
    }

    public function test_it_formats_four_names_with_oxford_comma(): void
    {
        $characters = Character::factory()->count(4)->sequence(
            ['name' => 'Arthas', 'reached_level_cap_at' => now()],
            ['name' => 'Jaina', 'reached_level_cap_at' => now()],
            ['name' => 'Thrall', 'reached_level_cap_at' => now()],
            ['name' => 'Sylvanas', 'reached_level_cap_at' => now()],
        )->create();

        $notification = new LevelCapAchieved($characters);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertStringContainsString('**Arthas**, **Jaina**, **Thrall**, and **Sylvanas**', $embed['description']);
    }

    public function test_it_includes_green_color_in_embed(): void
    {
        $character = Character::factory()->create(['name' => 'TestChar', 'reached_level_cap_at' => now()]);
        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertEquals(5763719, $embed['color']);
    }

    public function test_it_includes_timestamp_in_embed(): void
    {
        $character = Character::factory()->create(['name' => 'TestChar', 'reached_level_cap_at' => now()]);
        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayHasKey('timestamp', $embed);
    }

    public function test_it_includes_first_place_image_for_first_character(): void
    {
        $character = Character::factory()->create(['name' => 'FirstPlace', 'reached_level_cap_at' => now()]);
        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayHasKey('image', $embed);
        $this->assertEquals('https://regrowth.gg/images/raceto70_firstplace.webp', $embed['image']['url']);
    }

    public function test_it_includes_second_place_image_for_second_character(): void
    {
        Character::factory()->create(['name' => 'FirstPlace', 'reached_level_cap_at' => now()->subMinute()]);
        $character = Character::factory()->create(['name' => 'SecondPlace', 'reached_level_cap_at' => now()]);

        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayHasKey('image', $embed);
        $this->assertEquals('https://regrowth.gg/images/raceto70_secondplace.webp', $embed['image']['url']);
    }

    public function test_it_includes_third_place_image_for_third_character(): void
    {
        Character::factory()->create(['name' => 'FirstPlace', 'reached_level_cap_at' => now()->subMinutes(2)]);
        Character::factory()->create(['name' => 'SecondPlace', 'reached_level_cap_at' => now()->subMinute()]);
        $character = Character::factory()->create(['name' => 'ThirdPlace', 'reached_level_cap_at' => now()]);

        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayHasKey('image', $embed);
        $this->assertEquals('https://regrowth.gg/images/raceto70_thirdplace.webp', $embed['image']['url']);
    }

    public function test_it_does_not_include_image_for_fourth_or_later(): void
    {
        Character::factory()->create(['name' => 'FirstPlace', 'reached_level_cap_at' => now()->subMinutes(3)]);
        Character::factory()->create(['name' => 'SecondPlace', 'reached_level_cap_at' => now()->subMinutes(2)]);
        Character::factory()->create(['name' => 'ThirdPlace', 'reached_level_cap_at' => now()->subMinute()]);
        $character = Character::factory()->create(['name' => 'FourthPlace', 'reached_level_cap_at' => now()]);

        $notification = new LevelCapAchieved([$character]);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayNotHasKey('image', $embed);
    }

    public function test_it_does_not_include_image_for_multiple_characters(): void
    {
        $characters = Character::factory()->count(2)->sequence(
            ['name' => 'FirstPlace', 'reached_level_cap_at' => now()],
            ['name' => 'SecondPlace', 'reached_level_cap_at' => now()],
        )->create();

        $notification = new LevelCapAchieved($characters);
        $message = $notification->toDiscord(new \stdClass);

        $embed = $this->getEmbedFromMessage($message);

        $this->assertArrayNotHasKey('image', $embed);
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
