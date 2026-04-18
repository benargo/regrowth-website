<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

readonly class GuildAttendance implements Arrayable
{
    public function __construct(
        public string $code,
        public array $players,
        public Carbon $startTime,
        public ?Zone $zone = null,
    ) {}

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
