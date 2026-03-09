<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return list<string>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->permissions()->pluck('name')->all();
    }
}
