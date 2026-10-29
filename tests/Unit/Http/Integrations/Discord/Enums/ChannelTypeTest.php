<?php

namespace Tests\Unit\Http\Integrations\Discord\Enums;

use App\Http\Integrations\Discord\Enums\ChannelType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('discord-integration')]
#[Group('platform')]
class ChannelTypeTest extends TestCase
{
    #[Test]
    public function thread_types_lists_the_three_thread_cases(): void
    {
        $this->assertSame(
            [ChannelType::AnnouncementThread, ChannelType::PublicThread, ChannelType::PrivateThread],
            ChannelType::threadTypes(),
        );
    }
}
