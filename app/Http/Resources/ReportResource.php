<?php

namespace App\Http\Resources;

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
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time->toIso8601String(),
            'duration' => $this->duration,
            'zone' => [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
            ],
            'guild_tag' => $this->whenLoaded('guildTag', fn () => [
                'id' => $this->guildTag->id,
                'name' => $this->guildTag->name,
            ]),
            'characters' => $this->whenLoaded('characters', fn ($characters) => $characters
                ->sortBy('name')
                ->values()
                ->map(fn ($character) => [
                    'id' => $character->id,
                    'name' => $character->name,
                    'is_main' => $character->is_main,
                    'playable_class' => $character->playable_class,
                    'playable_race' => $character->playable_race,
                    'pivot' => [
                        'presence' => $character->pivot->presence,
                        'is_loot_councillor' => $character->pivot->is_loot_councillor,
                    ],
                    'rank' => $character->relationLoaded('rank')
                        ? ($character->rank ? [
                            'id' => $character->rank->id,
                            'position' => $character->rank->position,
                            'name' => $character->rank->name,
                            'count_attendance' => $character->rank->count_attendance,
                        ] : null)
                        : null,
                ])
                ->values()
                ->all()
            ),
            'linked_reports' => $this->whenLoaded('linkedReports', fn ($linkedReports) => $linkedReports
                ->map(fn ($linked) => [
                    'id' => $linked->id,
                    'title' => $linked->title,
                    'start_time' => $linked->start_time->toIso8601String(),
                    'zone' => [
                        'id' => $linked->zone->id,
                        'name' => $linked->zone->name,
                    ],
                    'pivot' => $linked->pivot ? [
                        'created_by' => $linked->pivot->creator
                            ? ['display_name' => $linked->pivot->creator->display_name]
                            : null,
                        'created_at' => $linked->pivot->created_at?->toIso8601String(),
                        'updated_at' => $linked->pivot->updated_at?->toIso8601String(),
                    ] : null,
                ])
                ->values()
                ->all()
            ),
            'linked_reports_count' => $this->whenCounted('linkedReports'),
        ];
    }
}
