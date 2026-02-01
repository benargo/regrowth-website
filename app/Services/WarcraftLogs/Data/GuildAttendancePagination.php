<?php

namespace App\Services\WarcraftLogs\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

readonly class GuildAttendancePagination
{
    /**
     * @param  array<GuildAttendance>  $data  List of items on the current page.
     * @param  int  $total  Number of total items selected by the query.
     * @param  int  $perPage  Number of items returned per page.
     * @param  int  $currentPage  Current page of the cursor.
     * @param  int  $from  Number of the first item returned.
     * @param  int  $to  Number of the last item returned.
     * @param  int  $lastPage  The last page (number of pages).
     * @param  bool  $hasMorePages  Determines if cursor has more pages after the current page.
     */
    public function __construct(
        public array $data,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $from,
        public int $to,
        public int $lastPage,
        public bool $hasMorePages,
    ) {}

    /**
     * @param  array{data: array, total: int, per_page: int, current_page: int, from: int, to: int, last_page: int, has_more_pages: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        $attendanceData = Arr::map(
            $data['data'] ?? [],
            fn (array $attendance) => GuildAttendance::fromArray($attendance),
        );

        return new self(
            data: $attendanceData,
            total: $data['total'],
            perPage: $data['per_page'],
            currentPage: $data['current_page'],
            from: $data['from'],
            to: $data['to'],
            lastPage: $data['last_page'],
            hasMorePages: $data['has_more_pages'],
        );
    }

    /**
     * @return array{data: array<GuildAttendance>, total: int, perPage: int, currentPage: int, from: int, to: int, lastPage: int, hasMorePages: bool}
     */
    public function toArray(): array
    {
        return [
            'data' => Arr::map($this->data, fn (GuildAttendance $attendance) => $attendance->toArray()),
            'total' => $this->total,
            'perPage' => $this->perPage,
            'currentPage' => $this->currentPage,
            'from' => $this->from,
            'to' => $this->to,
            'lastPage' => $this->lastPage,
            'hasMorePages' => $this->hasMorePages,
        ];
    }

    /**
     * Convert to a Laravel LengthAwarePaginator instance.
     */
    public function toLengthAwarePaginator(?string $path = null): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: $this->data,
            total: $this->total,
            perPage: $this->perPage,
            currentPage: $this->currentPage,
            options: ['path' => $path ?? request()?->path() ?? '/'],
        );
    }
}
