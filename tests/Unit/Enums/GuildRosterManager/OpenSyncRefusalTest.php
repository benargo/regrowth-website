<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\OpenSyncRefusal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class OpenSyncRefusalTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_three_cases(): void
    {
        $this->assertCount(3, OpenSyncRefusal::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('duplicate', OpenSyncRefusal::Duplicate->value);
        $this->assertSame('ticket_expired', OpenSyncRefusal::TicketExpired->value);
        $this->assertSame('revision_conflict', OpenSyncRefusal::RevisionConflict->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(OpenSyncRefusal::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        OpenSyncRefusal::from('bogus');
    }
}
