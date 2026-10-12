<?php

namespace Tests\Unit\Enums;

use App\Enums\PlannedAbsenceReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('raiding')]
class PlannedAbsenceReasonTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_cases(): void
    {
        $this->assertCount(3, PlannedAbsenceReason::cases());
    }

    /**
     * @return array<string, array{PlannedAbsenceReason, string, string}>
     */
    public static function reasonProvider(): array
    {
        return [
            'holiday' => [PlannedAbsenceReason::Holiday, 'On holiday', 'umbrella-beach'],
            'work' => [PlannedAbsenceReason::Work, 'Stuck at work', 'briefcase'],
            'other' => [PlannedAbsenceReason::Other, 'Other', 'question'],
        ];
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function each_case_has_correct_value(PlannedAbsenceReason $reason, string $value, string $icon): void
    {
        $this->assertSame($value, $reason->value);
        $this->assertSame($reason, PlannedAbsenceReason::from($value));
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function each_case_has_correct_icon(PlannedAbsenceReason $reason, string $value, string $icon): void
    {
        $this->assertSame($icon, $reason->icon());
    }

    #[Test]
    public function each_case_has_a_unique_non_empty_description(): void
    {
        $descriptions = array_map(
            fn (PlannedAbsenceReason $reason): string => $reason->description(),
            PlannedAbsenceReason::cases(),
        );

        foreach ($descriptions as $description) {
            $this->assertNotEmpty($description);
        }

        $this->assertSame($descriptions, array_unique($descriptions));
    }

    #[Test]
    public function other_description_mentions_the_notes(): void
    {
        $this->assertStringContainsString('notes', PlannedAbsenceReason::Other->description());
    }

    #[Test]
    public function try_from_returns_null_for_unknown_value(): void
    {
        $this->assertNull(PlannedAbsenceReason::tryFrom('Abducted by aliens'));
    }

    #[Test]
    public function default_constant_is_other(): void
    {
        $this->assertSame(PlannedAbsenceReason::Other, PlannedAbsenceReason::DEFAULT);
    }
}
