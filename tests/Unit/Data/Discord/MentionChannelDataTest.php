<?php

namespace Tests\Unit\Data\Discord;

use App\Data\Discord\MentionChannelData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class MentionChannelDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_to_snake_case_keys(): void
    {
        $channel = new MentionChannelData(
            id: '1234567890123456789',
            name: 'raid-signups',
            category: 'Raiding',
            isCategory: false,
            isDeleted: false,
        );

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'raid-signups',
            'category' => 'Raiding',
            'is_category' => false,
            'is_deleted' => false,
        ], $channel->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_a_null_category(): void
    {
        $channel = new MentionChannelData(
            id: '1234567890123456789',
            name: 'Archive',
            category: null,
            isCategory: true,
            isDeleted: true,
        );

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'Archive',
            'category' => null,
            'is_category' => true,
            'is_deleted' => true,
        ], $channel->toArray());
    }
}
