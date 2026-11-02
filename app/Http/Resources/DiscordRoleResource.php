<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a Discord role, including its colours and flags.
 * The user and permission counts are omitted unless loaded.
 * Optionally limited to the `badge` scope, which keeps only the name and primary colour.
 */
class DiscordRoleResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'tertiary_color' => $this->tertiary_color,
            'is_visible' => $this->is_visible,
            'grants_permissions' => $this->grants_permissions,
            'users_count' => $this->whenCounted('users'),
            'permissions_count' => $this->whenCounted('permissions'),
        ]);
    }

    /**
     * The fields kept by each named scope.
     *
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'badge' => ['name', 'primary_color'],
        ];
    }
}
