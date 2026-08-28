<?php

namespace App\Http\Resources;

use App\Models\GuildRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
            'sort_order' => $this->sort_order,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'count_attendance' => $this->count_attendance,
        ];
    }
}
