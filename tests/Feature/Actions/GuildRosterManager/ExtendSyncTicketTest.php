<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ExtendSyncTicket;
use App\Contracts\Actions\GuildRosterManager\SharesOpenTicketLifetime;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ExtendSyncTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
    }

    #[Test]
    public function it_renews_the_ticket_for_five_minutes_from_now(): void
    {
        $sync = Upload::factory()->staging()->create();
        $this->travel(4)->minutes();

        $ticketExpiresAt = ExtendSyncTicket::run($sync);

        $this->assertTrue($ticketExpiresAt->equalTo(now()->addSeconds(SharesOpenTicketLifetime::LIFETIME_SECONDS)));
        $this->assertTrue($sync->refresh()->ticket_expires_at->equalTo($ticketExpiresAt));
    }

    #[Test]
    public function it_never_extends_past_the_sync_expiry(): void
    {
        $sync = Upload::factory()->staging()->create();
        $this->travel(58)->minutes();

        $ticketExpiresAt = ExtendSyncTicket::run($sync);

        $this->assertTrue($ticketExpiresAt->equalTo($sync->expires_at));
        $this->assertTrue($sync->refresh()->ticket_expires_at->equalTo($sync->expires_at));
    }

    #[Test]
    public function it_never_changes_the_sync_expiry(): void
    {
        $sync = Upload::factory()->staging()->create();
        $expiresAt = $sync->expires_at->copy();
        $this->travel(10)->minutes();

        ExtendSyncTicket::run($sync);

        $this->assertTrue($sync->refresh()->expires_at->equalTo($expiresAt));
    }
}
