<?php

namespace App\Http\Resources\GuildRosterManager;

use App\Models\GuildRosterManager\UploadRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform one row outcome of a GRM upload with its status, reason, message, matched character's ID and slug
 * (when loaded), and whether the reason is an expected outcome.
 *
 * @mixin UploadRow
 */
class UploadRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'characterId' => $this->character_id,
            'characterSlug' => $this->whenLoaded('character', fn (): ?string => $this->character?->slug),
            'id' => $this->id,
            'message' => $this->message,
            'name' => $this->name,
            'reason' => $this->reason,
            'status' => $this->status,
            'meta' => [
                'is_expected' => $this->reason->isExpected(),
            ],
        ];
    }
}
