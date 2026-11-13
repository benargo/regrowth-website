<?php

namespace App\Http\Resources;

use App\Models\DesktopClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a desktop client with its owner's display name and whether it is still signed in.
 *
 * @mixin DesktopClient
 */
class DesktopClientResource extends JsonResource
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
            'owner' => $this->whenLoaded('user', fn () => $this->user->display_name),
            'isLinked' => $this->is_linked,
            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'lastUploadAt' => $this->last_upload_at?->toIso8601String(),
            'meta' => [
                'canRevoke' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
