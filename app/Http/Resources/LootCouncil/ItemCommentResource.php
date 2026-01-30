<?php

namespace App\Http\Resources\LootCouncil;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemCommentResource extends JsonResource
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
            'item' => $this->getItemData(),
            'user' => $this->getUserData(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => [
                'edit' => $request->user()?->can('edit-loot-comment', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete-loot-comment', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * Get item data for the response.
     *
     * @return array<string, mixed>
     */
    protected function getItemData()
    {
        if (! $this->relationLoaded('item')) {
            return $this->item_id;
        }

        return (new ItemResource($this->item))->toArray(request());
    }

    /**
     * Get user data for the response.
     *
     * @return array<string, mixed>
     */
    protected function getUserData()
    {
        if (! $this->relationLoaded('user')) {
            return $this->user_id;
        }

        return (new UserResource($this->user))->toArray(request());
    }
}
