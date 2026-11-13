<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class UploadSourceTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_two_cases(): void
    {
        $this->assertCount(2, UploadSource::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('manual', UploadSource::Manual->value);
        $this->assertSame('desktop', UploadSource::Desktop->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(UploadSource::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        UploadSource::from('bogus');
    }
}
