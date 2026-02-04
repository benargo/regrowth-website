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
            'discriminator' => $this->discriminator,
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'avatar' => $this->avatar_url,
            'banner' => $this->banner_url,
            'roles' => $this->discordRoles->pluck('id')->values()->toArray(),
            'highest_role' => $this->highestRole(),
        ];
    }
}
