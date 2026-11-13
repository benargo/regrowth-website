<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class UploadOutcomeTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_nine_cases(): void
    {
        $this->assertCount(9, UploadOutcome::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('received', UploadOutcome::Received->value);
        $this->assertSame('processing', UploadOutcome::Processing->value);
        $this->assertSame('processed', UploadOutcome::Processed->value);
        $this->assertSame('no_changes', UploadOutcome::NoChanges->value);
        $this->assertSame('superseded', UploadOutcome::Superseded->value);
        $this->assertSame('failed', UploadOutcome::Failed->value);
        $this->assertSame('staging', UploadOutcome::Staging->value);
        $this->assertSame('abandoned', UploadOutcome::Abandoned->value);
        $this->assertSame('expired', UploadOutcome::Expired->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(UploadOutcome::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        UploadOutcome::from('bogus');
    }
}
