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
        public readonly float $percentage,
        public readonly array $attendance,
        #[WithTransformer(ToResourceListTransformer::class, resource: PlannedAbsenceSummaryResource::class)]
        public readonly array $plannedAbsences,
        public readonly array|Optional $attendanceNames = new Optional,
    ) {}

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
