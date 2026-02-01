<?php

namespace App\Services\WarcraftLogs\Data;

use Carbon\Carbon;
use Illuminate\Support\Arr;

readonly class GuildAttendance
{
    public function __construct(
        /**
         * The code of the report for the raid night.
         *
         * @param  string  $code
         */
        public string $code,

        /**
         * The players that attended that raid night.
         *
         * @param  array<PlayerAttendance>  $players
         */
        public array $players,

        /**
         * The start time of the raid night.
         *
         * @param  Carbon  $startTime
         */
        public Carbon $startTime,

        /**
         * The principal zone of the raid night. This data is not to be queried or processed.
         *
         * @param  Zone|null  $zone
         */
        public ?Zone $zone = null,
    ) {}

    /**
     * @param  array{code: string, players: array, startTime: string, zone: array{id: int, name: string}|null}  $data
     *
     * @throws ModelNotFoundException
     */
    public static function fromArray(array $data): self
    {
        $players = Arr::map(
            $data['players'] ?? [],
            fn (array $player) => PlayerAttendance::fromArray($player),
        );

        return new self(
            code: $data['code'],
            players: $players,
            startTime: Carbon::createFromTimestampMs($data['startTime']),
            zone: isset($data['zone']) ? Zone::fromArray($data['zone']) : null,
        );
    }

    /**
     * @return array{code: string, players: array, startTime: string, zone: array{id: int, name: string}}
     */
    public function toArray(): array
    {
        $response = [
            'code' => $this->code,
            'players' => Arr::map(
                $this->players,
                fn (PlayerAttendance $player) => $player->toArray(),
            ),
            'startTime' => $this->startTime->valueOf(),
        ];

        if ($this->zone !== null) {
            $response['zone'] = $this->zone->toArray();
        }

        return $response;
    }

    /**
     * Filter players to only include those with matching names.
     *
     * @param  array<string>  $playerNames  Player names to include.
     * @return self New instance with filtered players.
     */
    public function filterPlayers(array $playerNames): self
    {
        $filteredPlayers = array_filter(
            $this->players,
            fn (PlayerAttendance $player) => in_array($player->name, $playerNames, strict: true),
        );

        return new self(
            code: $this->code,
            players: array_values($filteredPlayers),
            startTime: $this->startTime,
            zone: $this->zone,
        );
    }
}
