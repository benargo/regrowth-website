<?php

namespace App\Http\Resources;

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
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration' => $this->duration,
            'channel' => $this->channel->only('id', 'name', 'position'),
        ];

        if ($this->relationLoaded('raids')) {
            $data['raids'] = $this->raids->map->only('id', 'name')->values()->toArray();
        }

        return $data;
    }
}
