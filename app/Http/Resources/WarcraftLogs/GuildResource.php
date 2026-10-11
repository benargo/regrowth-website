<?php

namespace App\Http\Resources\WarcraftLogs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuildResource extends JsonResource
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
            'namespace' => [
                'value' => $this->namespace,
                'label' => $this->namespace->label(),
            ],
            'game_versions' => $this->whenLoaded('gameVersions', fn () => $this->gameVersions->toResourceCollection()->resolve($request)),
            'guild_tags' => $this->whenLoaded('guildTags', fn () => $this->guildTags->toResourceCollection()->resolve($request)),
            'game_versions_count' => $this->whenCounted('gameVersions'),
            'guild_tags_count' => $this->whenCounted('guildTags'),
            'reports_count' => $this->whenCounted('reports'),
        ];
    }
}
