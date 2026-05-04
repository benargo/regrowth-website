<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EventCollection extends ResourceCollection
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
                'start_time' => $model->start_time,
                'end_time' => $model->end_time,
                'duration' => $model->duration,
                'channel' => $model->channel->only('id', 'name', 'position'),
            ];

            if ($model->relationLoaded('raids')) {
                $data['raids'] = $model->raids()->select('id', 'name')->get();
            }

            return $data;
        })->toArray();
    }
}
