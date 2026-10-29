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
     * @return array<string, array{PlannedAbsenceReason, string, string, string}>
     */
    public static function reasonProvider(): array
    {
        return [
            'holiday' => [PlannedAbsenceReason::Holiday, 'On holiday', 'umbrella-beach', 'You\'re on holiday'],
            'work' => [PlannedAbsenceReason::Work, 'Stuck at work', 'briefcase', 'You\'re stuck at work'],
            'other' => [PlannedAbsenceReason::Other, 'Other', 'portal-exit', 'You\'re away'],
        ];
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function each_case_has_correct_value(PlannedAbsenceReason $reason, string $value, string $icon, string $navigationLabel): void
    {
        $this->assertSame($value, $reason->value);
        $this->assertSame($reason, PlannedAbsenceReason::from($value));
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function each_case_has_correct_icon(PlannedAbsenceReason $reason, string $value, string $icon, string $navigationLabel): void
    {
        $this->assertSame($icon, $reason->icon());
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function each_case_has_a_navigation_label(PlannedAbsenceReason $reason, string $value, string $icon, string $navigationLabel): void
    {
        $this->assertSame($navigationLabel, $reason->navigationLabel());
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
