<?php

namespace App\Http\Resources\WarcraftLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuildTagResource extends JsonResource
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
            'name' => $this->name,
            'count_attendance' => $this->count_attendance,
            'phase' => $this->phase,
        ];
    }
}
