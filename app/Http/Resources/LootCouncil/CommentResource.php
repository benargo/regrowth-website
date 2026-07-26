<?php

namespace App\Http\Resources\LootCouncil;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

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
            'item' => $this->getCommentable(),
            'user' => $this->getRelation('user'),
            'reactions' => $this->getReactions($request),
            'is_resolved' => $this->is_resolved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => [
                'edit' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false, // Remove later
                'react' => $request->user()?->can('react', $this->resource) ?? false,
                'resolve' => $request->user()?->can('markAsResolved', $this->resource) ?? false, // Remove later
            ],
        ];
    }

    /**
     * Get the commentable model's data if loaded, otherwise return the commentable_id.
     *
     * @return array<string, mixed>|string|null
     */
    protected function getCommentable(): mixed
    {
        if (! $this->relationLoaded('commentable')) {
            return $this->commentable_id;
        }

        if ($this->commentable === null) {
            return null;
        }

        return $this->commentable->toResource()->toArray(request());
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
            'user' => (new UserResource($this->user))->toArray(request()),
            default => $this->{$relation},
        };
    }

    /**
     * Get the comment's reactions with user data.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getReactions(Request $request): array
    {
        return Cache::tags(['db', 'lootcouncil'])->remember("comment:#{$this->id}:reactions", now()->addMinutes(10), function () use ($request) {
            return $this->reactions->map(fn ($reaction) => [
                'id' => $reaction->id,
                'user' => (new UserResource($reaction->user))->toArray($request),
                'created_at' => $reaction->created_at,
            ])->toArray();
        });
    }
}
