<?php

namespace App\Http\Resources\GuildRosterManager;

use App\Data\GuildRosterManager\RosterStateData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Transform a game version's roster state into a JSON:API `roster_states` resource object, identified by the game version's slug, with its revision, fingerprints and ban access.
 *
 * @mixin RosterStateData
 */
class RosterStateResource extends JsonApiResource
{
    /**
     * Get the resource's ID: the game version's slug.
     */
    public function toId(Request $request): string
    {
        return $this->slug;
    }

    /**
     * Get the resource's attributes, with each empty fingerprint map serialised as a JSON object.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $fingerprints = $this->fingerprints;

        foreach (['members', 'leavers', 'altGroups', 'bans'] as $group) {
            if (array_key_exists($group, $fingerprints)) {
                $fingerprints[$group] = (object) $fingerprints[$group];
            }
        }

        return [
            'revision' => $this->revision,
            'committedAt' => $this->committed_at?->toIso8601ZuluString(),
            'fingerprints' => $fingerprints,
            'banAccess' => $this->ban_access,
        ];
    }
}
