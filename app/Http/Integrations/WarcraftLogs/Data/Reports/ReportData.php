<?php

namespace App\Http\Integrations\WarcraftLogs\Data\Reports;

use App\Http\Integrations\WarcraftLogs\Data\Casts\MillisecondTimestampCast;
use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class ReportData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        #[WithCast(MillisecondTimestampCast::class)]
        public readonly Carbon $startTime,
        #[WithCast(MillisecondTimestampCast::class)]
        public readonly Carbon $endTime,
        public readonly ?GuildTagData $guildTag = null,
        public readonly ?ZoneData $zone = null,
    ) {}
}
