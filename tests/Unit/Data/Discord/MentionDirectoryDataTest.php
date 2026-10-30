<?php

namespace Tests\Unit\Data\Discord;

use App\Data\Discord\MentionChannelData;
use App\Data\Discord\MentionDirectoryData;
use App\Data\Discord\MentionEmojiData;
use App\Data\Discord\MentionRoleData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class MentionDirectoryDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_nested_channels_roles_and_emoji_to_arrays(): void
    {
        $directory = new MentionDirectoryData(
            channels: [new MentionChannelData(
                id: '1111111111111111111',
                name: 'raid-signups',
                category: 'Raiding',
                isCategory: false,
                isDeleted: false,
            )],
            roles: [new MentionRoleData(id: '2222222222222222222', name: 'Raider')],
            emoji: [new MentionEmojiData(
                id: '3333333333333333333',
                name: 'pepe_dance',
                animated: true,
                available: true,
                url: 'https://cdn.discordapp.com/emojis/3333333333333333333.gif',
            )],
        );

        $this->assertSame([
            'channels' => [[
                'id' => '1111111111111111111',
                'name' => 'raid-signups',
                'category' => 'Raiding',
                'is_category' => false,
                'is_deleted' => false,
            ]],
            'roles' => [[
                'id' => '2222222222222222222',
                'name' => 'Raider',
            ]],
            'emoji' => [[
                'id' => '3333333333333333333',
                'name' => 'pepe_dance',
                'animated' => true,
                'available' => true,
                'url' => 'https://cdn.discordapp.com/emojis/3333333333333333333.gif',
            ]],
        ], $directory->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_empty_lists(): void
    {
        $directory = new MentionDirectoryData(channels: [], roles: [], emoji: []);

        $this->assertSame([
            'channels' => [],
            'roles' => [],
            'emoji' => [],
        ], $directory->toArray());
    }
}
