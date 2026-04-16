<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

readonly class Report implements Arrayable
{
    public function __construct(
        public string $code,
        public string $title,
        public Carbon $startTime,
        public Carbon $endTime,
        public ?GuildTag $guildTag = null,
        public ?Zone $zone = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            title: $data['title'],
            startTime: Carbon::createFromTimestampMs($data['startTime']),
            endTime: Carbon::createFromTimestampMs($data['endTime']),
            guildTag: Arr::has($data, 'guildTag.id') ? GuildTag::find(Arr::get($data, 'guildTag.id')) : null,
            zone: isset($data['zone']) ? Zone::fromArray($data['zone']) : null,
        );
    }

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

        if ($this->zone instanceof Zone) {
            $response['zone'] = $this->zone->toArray();
        }

        return $response;
    }
}
