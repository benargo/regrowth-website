<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\DiscordMembersSearched;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('broadcasting')]
class DiscordMembersSearchedTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_requesting_users_private_channel(): void
    {
        $this->assertEquals(
            [new PrivateChannel('App.Models.User.100000000000000003')],
            $this->event()->broadcastOn(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_as_discord_members_searched(): void
    {
        $this->assertSame('DiscordMembersSearched', $this->event()->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_the_query_limit_and_failure_flag(): void
    {
        $this->assertSame(
            ['query' => 'test', 'limit' => 5, 'failed' => false],
            $this->event()->broadcastWith(),
        );
    }

    // ==================== helpers ====================

    private function event(): DiscordMembersSearched
    {
        return new DiscordMembersSearched('100000000000000003', 'test', 5, failed: false);
    }
}
