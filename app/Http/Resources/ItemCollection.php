<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'count' => $this->collection->count(),
            'data' => $this->collection,
            'comments' => [
                'count' => $this->aggregateCommentsCount(),
            ],
        ];
    }

    private function aggregateCommentsCount(): ?int
    {
        $counted = $this->collection->filter(
            fn ($item) => $item->comments_count !== null
        );

        if ($counted->isEmpty()) {
            return null;
        }

        return (int) $this->collection->sum('comments_count');
    }
}
