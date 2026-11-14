<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\CommitSyncStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class CommitSyncStatusTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, CommitSyncStatus::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('committed', CommitSyncStatus::Committed->value);
        $this->assertSame('revision_conflict', CommitSyncStatus::RevisionConflict->value);
        $this->assertSame('ticket_expired', CommitSyncStatus::TicketExpired->value);
        $this->assertSame('expired', CommitSyncStatus::Expired->value);
        $this->assertSame('abandoned', CommitSyncStatus::Abandoned->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(CommitSyncStatus::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        CommitSyncStatus::from('bogus');
    }
}
