<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;

class GuildAttendanceData extends Data
{
    /**
     * @param  array<PlayerAttendanceData>  $players
     */
    public function __construct(
        public readonly string $code,
        public readonly array $players,
        public readonly Carbon $startTime,
        public readonly ?ZoneData $zone = null,
    ) {}

    /**
     * @param  array{code: string, startTime: int|float, players?: array<array{name: string, type?: string, presence: int}>, zone?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        $players = Arr::map(
            $data['players'] ?? [],
            fn (array $player) => PlayerAttendanceData::from($player),
        );

        return new self(
            code: $data['code'],
            players: $players,
            startTime: Carbon::createFromTimestampMs($data['startTime']),
            zone: isset($data['zone']) ? ZoneData::from($data['zone']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'code' => $this->code,
            'players' => Arr::map(
                $this->players,
                fn (PlayerAttendanceData $player) => $player->toArray(),
            ),
            'startTime' => $this->startTime->valueOf(),
        ];

        if ($this->zone !== null) {
            $response['zone'] = $this->zone->toArray();
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string>  $playerNames
     */
    public function filterPlayers(array $playerNames): self
    {
        $filteredPlayers = array_filter(
            $this->players,
            fn (PlayerAttendanceData $player) => in_array($player->name, $playerNames, strict: true),
        );

        return new self(
            code: $this->code,
            players: array_values($filteredPlayers),
            startTime: $this->startTime,
            zone: $this->zone,
        );
    }
}
