<?php

namespace App\Data\Attendance;

use App\Data\Transformers\ToResourceListTransformer;
use App\Data\Transformers\ToResourceTransformer;
use App\Enums\Presence;
use App\Http\Resources\CharacterSummaryResource;
use App\Http\Resources\PlannedAbsenceSummaryResource;
use App\Models\Character;
use App\Models\PlannedAbsence;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

/**
 * One character's attendance across the matrix's raids.
 */
final class CharacterAttendanceData extends Resource
{
    /**
     * The attended share of the reports as a percentage rounded to two decimal
     * places, or zero when there are no reports.
     */
    public readonly float $percentage;

    /**
     * @param  array<int, Presence|null>  $attendance  newest raid first
     * @param  array<int, PlannedAbsence|null>  $plannedAbsences  one per raid column; emitted without the nulls, keyed by raid column
     * @param  array<int, array<int, string>>|Optional  $attendanceNames  one per raid column; omitted when optional
     */
    public function __construct(
        #[WithTransformer(ToResourceTransformer::class, resource: CharacterSummaryResource::class)]
        public readonly Character $character,
        #[Hidden]
        public readonly CarbonInterface $firstAttendance,
        #[Hidden]
        public readonly int $totalReports,
        #[Hidden]
        public readonly int $reportsAttended,
        public readonly array $attendance,
        #[WithTransformer(ToResourceListTransformer::class, resource: PlannedAbsenceSummaryResource::class)]
        public readonly array $plannedAbsences,
        public readonly array|Optional $attendanceNames = new Optional,
    ) {
        $this->percentage = $totalReports > 0 ? round($reportsAttended / $totalReports * 100, 2) : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        return ['character' => $array['character'], ...$array];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The row's character without relations except its playable class.
     */
    public function characterWithClassOnly(): Character
    {
        return $this->character->withoutRelations()->setRelation('playableClass', $this->character->playableClass);
    }

    /**
     * The attended share, as a percentage, of the newest four raids with a presence, or
     * null when fewer than three such raids exist.
     */
    public function recentAttendanceRate(): ?float
    {
        $recent = collect($this->attendance)->whereNotNull()->take(4);

        if ($recent->count() < 3) {
            return null;
        }

        return $recent->filter(fn (Presence $presence): bool => $presence->isAttended())->count() / $recent->count() * 100;
    }
}
