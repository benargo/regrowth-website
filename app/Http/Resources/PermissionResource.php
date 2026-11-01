<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Transform a permission, falling back to the headlined key when it has no label.
 * The holding role ids and role count are omitted unless loaded.
 */
class PermissionResource extends JsonResource
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
            'name' => $this->name,
            'label' => $this->label ?? Str::headline($this->name),
            'description' => $this->description,
            'permission_group_id' => $this->permission_group_id,
            'discord_role_ids' => $this->whenLoaded('discordRoles', fn () => $this->discordRoles->pluck('id')->values()),
            'discord_roles_count' => $this->whenCounted('discordRoles'),
        ];
    }
}
