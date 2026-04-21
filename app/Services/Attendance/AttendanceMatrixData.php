<?php

namespace App\Services\Attendance;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class AttendanceMatrixData extends Data
{
    public function __construct(
        /** @var Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}> */
        public readonly Collection $raids,
        /** @var Collection<int, CharacterAttendanceRowData> */
        public readonly Collection $rows,
    ) {}

    /**
     * @return array{raids: array<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>, rows: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'raids' => $this->raids->values()->all(),
            'rows' => $this->rows->map(fn (CharacterAttendanceRowData $row) => $row->toArray())->values()->all(),
        ];
    }

    /**
     * @return array{raids: array<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>, rows: array<int, array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
