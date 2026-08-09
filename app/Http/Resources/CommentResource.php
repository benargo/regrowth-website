<?php

namespace App\Http\Resources;

use App\Models\CommentReaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * A soft-deleted comment is masked here rather than in the frontend: the
     * deleted body must not reach the client at all, and every permission is
     * forced false so no action is offered against a tombstone.
     *
     * `commentable` and `user` are resolved through their own Resource class
     * inside the whenLoaded closure (rather than returned bare) because
     * neither model defines a $hidden list narrow enough to expose raw —
     * returning them unwrapped would leak columns UserResource/ItemResource
     * deliberately omit or reshape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDeleted = $this->resource->trashed();

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'body' => $isDeleted ? null : $this->body,
            'commentable' => $this->whenLoaded('commentable', fn () => $this->commentable?->toResource()->resolve($request)),
            'user' => $this->whenLoaded('user', fn () => $this->user->toResource()->resolve($request)),
            'reactions' => $this->whenLoaded('reactions', fn () => CommentReactionResource::collection($this->reactions)->resolve($request)),
            'replies' => $this->whenLoaded('replies', fn () => self::collection($this->replies)->resolve($request)),
            'replies_count' => (int) ($this->replies_count ?? 0),
            'is_resolved' => $this->is_resolved,
            'is_deleted' => $isDeleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'permissions' => $isDeleted ? self::deniedPermissions() : [
                'edit' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'react' => $request->user()?->can('create', [CommentReaction::class, $this->resource]) ?? false,
                'resolve' => $request->user()?->can('markAsResolved', $this->resource) ?? false,
                'reply' => $request->user()?->can('reply', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * Get the permission set offered against a tombstoned comment.
     *
     * @return array<string, bool>
     */
    protected static function deniedPermissions(): array
    {
        return [
            'edit' => false,
            'delete' => false,
            'react' => false,
            'resolve' => false,
            'reply' => false,
        ];
    }
}
