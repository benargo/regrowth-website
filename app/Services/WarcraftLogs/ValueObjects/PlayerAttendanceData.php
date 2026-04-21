<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Spatie\LaravelData\Data;

class PlayerAttendanceData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $presence,
    ) {}
}
