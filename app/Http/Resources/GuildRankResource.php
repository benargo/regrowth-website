<?php

namespace App\Http\Resources;

use App\Models\GuildRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuildRankResource extends JsonResource
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = GuildRank::class;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'name' => $this->name,
            'count_attendance' => $this->count_attendance,
        ];
    }
}
