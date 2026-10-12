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
    public function it_has_exactly_ten_cases(): void
    {
        $this->assertCount(10, PlannedAbsenceReason::cases());
    }

    /**
     * @return array<string, array{PlannedAbsenceReason, string, string}>
     */
    public static function reasonProvider(): array
    {
        return [
            'bereavement' => [PlannedAbsenceReason::Bereavement, 'Bereavement', 'heart-broken'],
            'caring' => [PlannedAbsenceReason::Caring, 'Caring for someone', 'hand-holding-medical'],
            'education' => [PlannedAbsenceReason::Education, 'School or education', 'graduation-cap'],
            'event' => [PlannedAbsenceReason::Event, 'At a concert', 'guitar-electric'],
            'family' => [PlannedAbsenceReason::Family, 'Family and social obligations', 'baby'],
            'holiday' => [PlannedAbsenceReason::Holiday, 'On holiday', 'umbrella-beach'],
            'medical' => [PlannedAbsenceReason::Medical, 'Medical recovery', 'clinic-medical'],
            'religion' => [PlannedAbsenceReason::Religion, 'Religious observance', 'praying-hands'],
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
    public function other_description_mentions_an_officer(): void
    {
        $this->assertStringContainsString('officer', PlannedAbsenceReason::Other->description());
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
