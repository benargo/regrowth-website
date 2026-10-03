<?php

namespace App\Http\Integrations\WarcraftLogs\Data\Attendance;

use App\Http\Integrations\WarcraftLogs\Data\Casts\MillisecondTimestampCast;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class GuildAttendanceData extends Data
{
    /**
     * @param  array<int, PlayerAttendanceData>  $players
     */
    public function __construct(
        public readonly string $code,
        #[DataCollectionOf(PlayerAttendanceData::class)]
        public readonly array $players,
        #[WithCast(MillisecondTimestampCast::class)]
        public readonly Carbon $startTime,
        public readonly ?ZoneData $zone = null,
    ) {}

    /**
     * WCL's `players` list is nullable; treat a null or missing list as no players.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['players'] ??= [];

        return $properties;
    }

    /**
     * @param  array<int, string>  $playerNames
     */
    public function filterPlayers(array $playerNames): self
    {
        $players = collect($this->players)
            ->filter(fn (PlayerAttendanceData $player): bool => in_array($player->name, $playerNames, strict: true))
            ->values()
            ->all();

        return new self(
            code: $this->code,
            players: $players,
            startTime: $this->startTime,
            zone: $this->zone,
        );
    }
}
