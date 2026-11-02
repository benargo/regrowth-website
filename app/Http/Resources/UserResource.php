<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'display_name' => $this->display_name,
            'avatar' => $this->avatar_thumb_url,
            'highest_role' => $this->highestRole()?->toResource(DiscordRoleResource::class)->scope('badge'),
            'admin' => $this->is_admin,
        ];
    }
}
