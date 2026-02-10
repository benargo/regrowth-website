<?php

namespace App\Http\Resources\LootCouncil;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
            'body' => $this->body,
            'item' => $this->getRelation('item'),
            'user' => $this->getRelation('user'),
            'is_resolved' => $this->is_resolved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => [
                'edit' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'resolve' => $request->user()?->can('markAsResolved', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * Get a related model's data if it's loaded, otherwise return the foreign key ID.
     *
     * @return array<string, mixed>|string|int
     */
    protected function getRelation(string $relation): mixed
    {
        if (! $this->relationLoaded($relation)) {
            return $this->{"{$relation}_id"};
        }

        return match ($relation) {
            'item' => (new ItemResource($this->item))->toArray(request()),
            'user' => (new UserResource($this->user))->toArray(request()),
            default => $this->{$relation},
        };
    }
}
