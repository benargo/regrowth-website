<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LootCouncillorCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array{total: int, mains: int, alts: int}}
     */
    public function toArray(Request $request): array
    {
        $mains = $this->collection->where('is_main', true)->sortBy('name')->values();
        $alts = $this->collection->where('is_main', false);

        return [
            'data' => CharacterResource::collection($mains)->resolve($request),
            'meta' => [
                'total' => $this->collection->count(),
                'mains' => $mains->count(),
                'alts' => $alts->count(),
            ],
        ];
    }
}
