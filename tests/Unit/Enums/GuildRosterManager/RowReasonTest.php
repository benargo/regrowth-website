<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('characters')]
class RowReasonTest extends TestCase
{
    #[Test]
    public function only_expected_reasons_are_expected(): void
    {
        $expected = [
            RowReason::BelowMinimumLevel,
            RowReason::NotTracked,
            RowReason::NotConfirmedInGuild,
            RowReason::PlaceholderName,
        ];

        foreach (RowReason::cases() as $reason) {
            $this->assertSame(in_array($reason, $expected, true), $reason->isExpected(), $reason->name);
        }
    }

    #[Test]
    public function it_has_snake_case_backing_values(): void
    {
        $this->assertCount(16, RowReason::cases());
        $this->assertSame('below_minimum_level', RowReason::BelowMinimumLevel->value);
        $this->assertSame('not_confirmed_in_guild', RowReason::NotConfirmedInGuild->value);
        $this->assertSame('placeholder_name', RowReason::PlaceholderName->value);
        $this->assertSame('main_changed', RowReason::MainChanged->value);
        $this->assertSame('name_clash', RowReason::NameClash->value);
        $this->assertSame('error', RowReason::Error->value);
    }
}
