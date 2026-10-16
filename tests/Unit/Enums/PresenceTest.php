<?php

namespace Tests\Unit\Enums;

use App\Enums\Presence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
#[Group('raiding')]
class PresenceTest extends TestCase
{
    #[Test]
    public function it_backs_each_case_with_the_stored_pivot_value(): void
    {
        $this->assertSame(0, Presence::Absent->value);
        $this->assertSame(1, Presence::Present->value);
        $this->assertSame(2, Presence::Benched->value);
    }

    #[Test]
    public function priority_ranks_present_over_benched_over_absent(): void
    {
        $this->assertSame(2, Presence::Present->priority());
        $this->assertSame(1, Presence::Benched->priority());
        $this->assertSame(0, Presence::Absent->priority());
    }

    #[Test]
    #[DataProvider('attendedCases')]
    public function is_attended_is_true_for_present_and_benched(Presence $presence, bool $expected): void
    {
        $this->assertSame($expected, $presence->isAttended());
    }

    /**
     * @return array<string, array{Presence, bool}>
     */
    public static function attendedCases(): array
    {
        return [
            'present' => [Presence::Present, true],
            'benched' => [Presence::Benched, true],
            'absent' => [Presence::Absent, false],
        ];
    }
}
