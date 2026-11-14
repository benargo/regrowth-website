<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\RowStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('characters')]
class RowStatusTest extends TestCase
{
    #[Test]
    public function it_has_the_expected_cases_and_values(): void
    {
        $this->assertSame(
            ['processed', 'skipped', 'warning', 'failed'],
            array_map(fn (RowStatus $status) => $status->value, RowStatus::cases()),
        );
    }
}
