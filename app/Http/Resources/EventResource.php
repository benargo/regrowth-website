<?php

namespace App\Http\Resources;

use App\Models\Character;
use App\Models\Raid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'duration' => $this->duration,
            'channel' => $this->channel->only('id', 'name', 'position')->toArray(),
        ];

        if ($this->relationLoaded('raids')) {
            $data['raids'] = $this->raids->map(function (Raid $raid) {
                return [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'slug' => $raid->slug,
                    'difficulty' => $raid->difficulty,
                    'max_players' => $raid->max_players,
                ];
            })->values()->all();
        }

        if ($this->relationLoaded('characters')) {
            $data['characters'] = $this->characters->map(function (Character $character) {
                return [
                    'id' => $character->id,
                    'name' => $character->name,
                ];
            })->values()->all();
        }

        return $data;
    }
}
