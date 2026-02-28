<?php

namespace App\Services\AttendanceCalculator;

use Carbon\Carbon;

class AttendanceMatrixFilters
{
    public function __construct(
        /** @var array<int, int> */
        public readonly array $rankIds = [],
        /** @var array<int, int> */
        public readonly array $zoneIds = [],
        /** @var array<int, int> */
        public readonly array $guildTagIds = [],
        public readonly ?Carbon $sinceDate = null,
        public readonly ?Carbon $beforeDate = null,
    ) {}
}
