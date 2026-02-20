<?php

namespace App\Http\Resources\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuildTagResource extends JsonResource
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = GuildTag::class;

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
