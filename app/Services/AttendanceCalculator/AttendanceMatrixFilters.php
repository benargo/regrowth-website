<?php

namespace App\Services\AttendanceCalculator;

use App\Models\Character;
use Carbon\Carbon;

class AttendanceMatrixFilters
{
    public function __construct(
        public readonly ?Character $character = null,
        /** @var array<int, int> */
        public readonly array $rankIds = [],
        /** @var array<int, int>|null null means no zone filter; [] means filter for no zones */
        public readonly ?array $zoneIds = null,
        /** @var array<int, int> */
        public readonly array $guildTagIds = [],
        public readonly ?Carbon $sinceDate = null,
        public readonly ?Carbon $beforeDate = null,
        public readonly bool $includeLinkedCharacters = false,
    ) {}
}
