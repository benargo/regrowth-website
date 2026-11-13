<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\SyncRowSkipReason;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class SyncRowSkipReasonTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_four_cases(): void
    {
        $this->assertCount(4, SyncRowSkipReason::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('older', SyncRowSkipReason::Older->value);
        $this->assertSame('future_time', SyncRowSkipReason::FutureTime->value);
        $this->assertSame('no_evidence', SyncRowSkipReason::NoEvidence->value);
        $this->assertSame('forbidden', SyncRowSkipReason::Forbidden->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(SyncRowSkipReason::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        SyncRowSkipReason::from('bogus');
    }
}
