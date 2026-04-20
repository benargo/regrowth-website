<?php

namespace App\Services\Attendance;

use App\Models\Character;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class CharacterAttendanceStats implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly Character $character,
        public readonly Carbon $firstAttendance,
        public readonly int $totalReports,
        public readonly int $reportsAttended,
        public readonly float $percentage,
    ) {}

    /**
     * @return array{id: int, name: string, firstAttendance: string, totalReports: int, reportsAttended: int, percentage: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->character->id,
            'name' => $this->character->name,
            'firstAttendance' => $this->firstAttendance->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'totalReports' => $this->totalReports,
            'reportsAttended' => $this->reportsAttended,
            'percentage' => $this->percentage,
        ];
    }

    /**
     * @return array{id: int, name: string, firstAttendance: string, totalReports: int, reportsAttended: int, percentage: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
