<?php

namespace App\Http\Integrations\WarcraftLogs\Data\Attendance;

use Spatie\LaravelData\Data;

final class PlayerAttendanceData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $presence,
    ) {}
}
