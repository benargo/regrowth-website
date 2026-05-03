<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use App\Models\GuildTag;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;

class ReportData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly ?GuildTag $guildTag = null,
        public readonly ?ZoneData $zone = null,
    ) {}

    /**
     * @param  array{code: string, title: string, startTime: int|float, endTime: int|float, guildTag?: array{id: int, name: string}, zone?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            title: $data['title'],
            startTime: Carbon::createFromTimestampMs($data['startTime']),
            endTime: Carbon::createFromTimestampMs($data['endTime']),
            guildTag: Arr::has($data, 'guildTag.id') ? GuildTag::find(Arr::get($data, 'guildTag.id')) : null,
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
            'title' => $this->title,
            'startTime' => $this->startTime->valueOf(),
            'endTime' => $this->endTime->valueOf(),
        ];

        if ($this->guildTag instanceof GuildTag) {
            $response['guildTag'] = [
                'id' => $this->guildTag->id,
                'name' => $this->guildTag->name,
            ];
        }

        if ($this->zone instanceof ZoneData) {
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
}
