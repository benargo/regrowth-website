<?php

namespace App\Data\GuildRosterManager;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

final class LatestUploadData extends Data
{
    public function __construct(
        public readonly Carbon $lastModified,
        public readonly int $memberCount,
    ) {}
}
