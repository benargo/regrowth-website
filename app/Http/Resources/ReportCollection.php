<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($model) {
            $data = [
                'id' => $model->id,
                'title' => $model->title,
                'start_time' => $model->start_time->toIso8601String(),
                'end_time' => $model->end_time->toIso8601String(),
                'duration' => $model->duration,
                'zone' => [
                    'id' => $model->zone->id,
                    'name' => $model->zone->name,
                ],
                'guild_tag' => $model->relationLoaded('guildTag') && $model->guildTag
                    ? ['id' => $model->guildTag->id, 'name' => $model->guildTag->name]
                    : null,
                'linked_reports_count' => $model->linked_reports_count ?? null,
            ];

            return $data;
        })->toArray();
    }
}
