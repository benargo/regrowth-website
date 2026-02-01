<?php

namespace App\Services\WarcraftLogs\Data;

use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Arr;

readonly class Guild
{
    /**
     * @param  array<int, GuildTag>  $tags
     */
    public function __construct(
        public int $id,
        public string $name,
        public Server $server,
        public Faction $faction,
        public array $tags = [],
        public ?GuildAttendancePagination $attendance = null,
    ) {}

    /**
     * @param  array{id: int, name: string, server: array, faction: array, tags?: array}  $data
     */
    public static function fromArray(array $data): self
    {
        $attendanceData = isset($data['attendance']) ? GuildAttendancePagination::fromArray($data['attendance']) : null;

        $tags = Arr::map(
            $data['tags'] ?? [],
            fn (array $tag) => GuildTag::updateOrCreate(['id' => $tag['id']], $tag),
        );

        return new self(
            id: $data['id'],
            name: $data['name'],
            attendance: $attendanceData,
            server: Server::fromArray($data['server']),
            faction: Faction::fromArray($data['faction']),
            tags: $tags,
        );
    }

    /**
     * @return array{id: int, name: string, attendance: array|null, server: array, faction: array, tags: array}
     */
    public function toArray(): array
    {
        $returnData = [
            'id' => $this->id,
            'name' => $this->name,
            'server' => $this->server->toArray(),
            'faction' => $this->faction->toArray(),
            'tags' => Arr::map($this->tags, fn (GuildTag $tag) => $tag->toArray()),
        ];

        if ($this->attendance instanceof GuildAttendancePagination) {
            $returnData['attendance'] = $this->attendance->toArray();
        }

        return $returnData;
    }
}
