<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array. Admins receive every permission,
     * matching User::isAuthorizedTo().
     *
     * @return list<string>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource->is_admin) {
            return Permission::pluck('name')->all();
        }

        return $this->resource->permissions()->pluck('name')->all();
    }
}
