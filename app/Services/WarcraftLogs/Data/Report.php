<?php

namespace App\Services\WarcraftLogs\Data;

use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Support\Arr;

readonly class Report
{
    public function __construct(
        /**
         * The unique code of the report.
         */
        public string $code,

        /**
         * The title of the report.
         */
        public string $title,

        /**
         * The start time of the report.
         */
        public Carbon $startTime,

        /**
         * The end time of the report.
         */
        public Carbon $endTime,

        /**
         * The guild tag associated with the report.
         */
        public ?GuildTag $guildTag = null,

        /**
         * The principal zone of the report.
         */
        public ?Zone $zone = null,
    ) {}

    /**
     * @param  array{code: string, title: string, startTime: float, endTime: float, guildTag?: array{id: int, name: string}, zone?: array{id: int, name: string}}  $data
     */
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

    /**
     * @return array{code: string, title: string, startTime: float, endTime: float, guildTag?: array{id: int, name: string}, zone?: array{id: int, name: string}}
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

        if ($this->zone instanceof Zone) {
            $response['zone'] = $this->zone->toArray();
        }

        return $response;
    }
}
