<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class UploadStatusTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, UploadStatus::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('current', UploadStatus::Current->value);
        $this->assertSame('outdated', UploadStatus::Outdated->value);
        $this->assertSame('stale', UploadStatus::Stale->value);
        $this->assertSame('missing', UploadStatus::Missing->value);
        $this->assertSame('unknown', UploadStatus::Unknown->value);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_can_be_created_from_each_valid_string(): void
    {
        foreach (UploadStatus::cases() as $case) {
            $this->assertSame($case, UploadStatus::from($case->value));
            $this->assertSame($case, UploadStatus::tryFrom($case->value));
        }
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(UploadStatus::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        UploadStatus::from('bogus');
    }
}
