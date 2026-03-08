<?php

namespace App\Http\Resources\WarcraftLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'guild_tag' => new GuildTagResource($this->whenLoaded('guildTag')),
            'zone' => [
                'id' => $this->zone_id,
                'name' => $this->zone_name,
            ],
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
            'linked_reports' => LinkedReportResource::collection($this->whenLoaded('linkedReports')),
            'linked_reports_count' => $this->whenCounted('linkedReports'),
        ];
    }
}
