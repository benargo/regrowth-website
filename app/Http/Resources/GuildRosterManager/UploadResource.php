<?php

namespace App\Http\Resources\GuildRosterManager;

use App\Http\Resources\GameVersionResource;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a GRM upload or desktop sync with its source, outcome, start time, row counts, and, when loaded,
 * its game version, uploader name and device name.
 *
 * @mixin Upload
 */
class UploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device' => $this->whenLoaded('device', fn (): ?array => $this->device ? ['name' => $this->device->name] : null),
            'errorCount' => $this->error_count,
            'gameVersion' => $this->whenLoaded('gameVersion', fn (): GameVersionResource => $this->gameVersion->toResource(GameVersionResource::class)->scope('switcher')),
            'id' => $this->id,
            'outcome' => $this->outcome,
            'processedCount' => $this->processed_count,
            'skippedCount' => $this->skipped_count,
            'source' => $this->source,
            'startedAt' => $this->captured_at?->toIso8601String(),
            'uploader' => $this->whenLoaded('user', fn (): ?array => $this->user ? ['name' => $this->user->display_name] : null),
            'warningCount' => $this->warning_count,
        ];
    }
}
