<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentReactionResource extends JsonResource
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
            'comment_id' => $this->comment_id,
            'user' => $this->whenLoaded('user', function () use ($request) {
                return $this->user->toResource()->resolve($request);
            }),
            'created_at' => $this->created_at,
        ];
    }
}
