<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\IssueOpenTicket;
use App\Actions\GuildRosterManager\VerifyOpenTicket;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(IssueOpenTicket::class)]
#[CoversClass(VerifyOpenTicket::class)]
#[Group('characters')]
class OpenTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->user = User::factory()->create();
        $this->gameVersion = GameVersion::factory()->create();
    }

    #[Test]
    public function it_verifies_a_fresh_ticket_for_the_same_user_version_and_revision(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);

        $expiresAt = VerifyOpenTicket::run($ticket, $this->user, $this->gameVersion, 4);

        $this->assertNotNull($expiresAt);
        $this->assertSame(now()->addSeconds(IssueOpenTicket::LIFETIME_SECONDS)->getTimestamp(), $expiresAt->getTimestamp());
        $this->assertSame(config('app.timezone'), $expiresAt->getTimezone()->getName());
    }

    #[Test]
    public function it_rejects_a_ticket_after_its_lifetime(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);

        $this->travel(301)->seconds();

        $this->assertNull(VerifyOpenTicket::run($ticket, $this->user, $this->gameVersion, 4));
    }

    #[Test]
    public function it_rejects_a_ticket_for_another_user(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);

        $this->assertNull(VerifyOpenTicket::run($ticket, User::factory()->create(), $this->gameVersion, 4));
    }

    #[Test]
    public function it_rejects_a_ticket_for_another_game_version(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);

        $this->assertNull(VerifyOpenTicket::run($ticket, $this->user, GameVersion::factory()->create(), 4));
    }

    #[Test]
    public function it_rejects_a_ticket_for_another_revision(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);

        $this->assertNull(VerifyOpenTicket::run($ticket, $this->user, $this->gameVersion, 5));
    }

    #[Test]
    public function it_rejects_a_missing_or_tampered_ticket(): void
    {
        $ticket = IssueOpenTicket::run($this->user, $this->gameVersion, 4);
        $tampered = substr($ticket, 0, -3).(str_ends_with(substr($ticket, 0, -2), 'A') ? 'B' : 'A').substr($ticket, -2);

        $this->assertNull(VerifyOpenTicket::run(null, $this->user, $this->gameVersion, 4));
        $this->assertNull(VerifyOpenTicket::run('abc', $this->user, $this->gameVersion, 4));
        $this->assertNull(VerifyOpenTicket::run($tampered, $this->user, $this->gameVersion, 4));
    }

    #[Test]
    public function it_rejects_a_ticket_whose_claims_are_not_an_array_or_miss_a_claim(): void
    {
        $claims = ['u' => $this->user->id, 'g' => $this->gameVersion->id, 'r' => 4, 'e' => now()->addMinutes(5)->getTimestamp()];

        $this->assertNull(VerifyOpenTicket::run(Crypt::encryptString(json_encode('not-an-array')), $this->user, $this->gameVersion, 4));

        foreach (array_keys($claims) as $missing) {
            $incomplete = array_diff_key($claims, [$missing => true]);

            $this->assertNull(
                VerifyOpenTicket::run(Crypt::encryptString(json_encode($incomplete)), $this->user, $this->gameVersion, 4),
                "A ticket without the {$missing} claim must be rejected.",
            );
        }

        $this->assertNotNull(VerifyOpenTicket::run(Crypt::encryptString(json_encode($claims)), $this->user, $this->gameVersion, 4));
    }
}
