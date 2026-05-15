<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSummaryResource extends JsonResource
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
        ];

        try {
            $data['channel'] = $this->channel->only('id', 'name', 'position')->toArray();
        } catch (\Exception $e) {
            // Discord API unavailable — omit channel
        }

        return $data;
    }
}
