<?php

namespace App\Http\Resources\GuildRosterManager;

use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Transform a game version into a JSON:API `targets` resource object for Guild Roster Manager sync, identified by the game version's slug, with its client folder, guild key and SavedVariables glob.
 *
 * @mixin GameVersion
 */
class TargetResource extends JsonApiResource
{
    /**
     * Get the resource's ID: the game version's slug.
     */
    public function toId(Request $request): string
    {
        return $this->slug;
    }

    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
            'clientFolder' => $this->wow_client_folder,
            'guildKey' => $this->grm_guild_key,
            'clockToleranceSeconds' => config('services.grm.clock_tolerance_seconds'),
            'savedVariablesGlob' => "{$this->wow_client_folder}/WTF/Account/*/SavedVariables/Guild_Roster_Manager.lua",
        ];
    }
}
