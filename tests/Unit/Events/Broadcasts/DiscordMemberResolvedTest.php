<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\DiscordMemberResolved;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('broadcasting')]
class DiscordMemberResolvedTest extends TestCase
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
    public function it_broadcasts_as_discord_member_resolved(): void
    {
        $this->assertSame('DiscordMemberResolved', $this->event()->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_the_member_id_and_status(): void
    {
        $this->assertSame(
            ['memberId' => '999999999999999999', 'status' => 'ready'],
            $this->event()->broadcastWith(),
        );
    }

    // ==================== helpers ====================

    private function event(): DiscordMemberResolved
    {
        return new DiscordMemberResolved('100000000000000003', '999999999999999999', 'ready');
    }
}
