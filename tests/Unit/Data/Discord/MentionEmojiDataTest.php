<?php

namespace Tests\Unit\Data\Discord;

use App\Data\Discord\MentionEmojiData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class MentionEmojiDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_the_emoji_fields(): void
    {
        $emoji = new MentionEmojiData(
            id: '1234567890123456789',
            name: 'pepe_dance',
            animated: true,
            available: false,
            url: 'https://cdn.discordapp.com/emojis/1234567890123456789.gif',
        );

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'pepe_dance',
            'animated' => true,
            'available' => false,
            'url' => 'https://cdn.discordapp.com/emojis/1234567890123456789.gif',
        ], $emoji->toArray());
    }
}
