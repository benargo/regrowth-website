<?php

namespace Tests\Unit\Enums\GuildRosterManager;

use App\Enums\GuildRosterManager\RowVerdict;
use App\Enums\GuildRosterManager\SyncRowSkipReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Group('characters')]
class RowVerdictTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, RowVerdict::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('no_op', RowVerdict::NoOp->value);
        $this->assertSame('apply', RowVerdict::Apply->value);
        $this->assertSame('older', RowVerdict::Older->value);
        $this->assertSame('future', RowVerdict::Future->value);
        $this->assertSame('forbidden', RowVerdict::Forbidden->value);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_rejects_an_invalid_string(): void
    {
        $this->assertNull(RowVerdict::tryFrom('bogus'));

        $this->expectException(ValueError::class);

        RowVerdict::from('bogus');
    }

    #[Test]
    #[Group('happy-path')]
    #[DataProvider('skipReasonProvider')]
    public function it_maps_each_verdict_to_its_skip_reason(RowVerdict $verdict, ?SyncRowSkipReason $expected): void
    {
        $this->assertSame($expected, $verdict->skipReason());
    }

    /**
     * @return array<string, array{RowVerdict, SyncRowSkipReason|null}>
     */
    public static function skipReasonProvider(): array
    {
        return [
            'no op' => [RowVerdict::NoOp, null],
            'apply' => [RowVerdict::Apply, null],
            'older' => [RowVerdict::Older, SyncRowSkipReason::Older],
            'future' => [RowVerdict::Future, SyncRowSkipReason::FutureTime],
            'forbidden' => [RowVerdict::Forbidden, SyncRowSkipReason::Forbidden],
        ];
    }
}
