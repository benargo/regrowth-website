<?php

namespace Tests\Unit\Models;

use App\Models\DiscordEmoji;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('discord-integration')]
class DiscordEmojiTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordEmoji::class;
    }

    #[Test]
    public function it_uses_the_discord_emoji_table(): void
    {
        $model = new DiscordEmoji;

        $this->assertSame('discord_emoji', $model->getTable());
        $this->assertSame('string', $model->getKeyType());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new DiscordEmoji;

        $this->assertFillableAttribute($model, [
            'id',
            'name',
            'animated',
            'available',
        ]);
    }

    #[Test]
    public function it_casts_animated_and_available(): void
    {
        $emoji = $this->make(['animated' => 1, 'available' => 0]);

        $this->assertTrue($emoji->animated);
        $this->assertFalse($emoji->available);
    }

    #[Test]
    public function url_is_a_png_for_a_static_emoji(): void
    {
        $emoji = $this->make(['id' => '123456789012345678', 'animated' => false]);

        $this->assertSame('https://cdn.discordapp.com/emojis/123456789012345678.png', $emoji->url);
    }

    #[Test]
    public function url_is_a_gif_for_an_animated_emoji(): void
    {
        $emoji = $this->make(['id' => '123456789012345678', 'animated' => true]);

        $this->assertSame('https://cdn.discordapp.com/emojis/123456789012345678.gif', $emoji->url);
    }
}
