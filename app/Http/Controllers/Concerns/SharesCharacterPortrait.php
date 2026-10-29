<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use Illuminate\Http\Request;

/**
 * Backs the optional `character_portrait` prop that the CharacterSearch picker
 * partially reloads once a character is picked, and again when its portrait
 * job broadcasts.
 */
trait SharesCharacterPortrait
{
    /**
     * Queue a refresh of the requested character's portrait if it is stale,
     * and return the portrait currently stored.
     *
     * @return array{id: int, portrait_url: string|null}
     */
    protected function characterPortrait(Request $request): array
    {
        $validated = $request->validate([
            'character_portrait' => ['required', 'integer', 'exists:characters,id'],
        ]);

        $character = Character::with('media')->findOrFail($validated['character_portrait']);

        RefreshCharacterPortrait::queueIfStale($character);

        return [
            'id' => $character->id,
            'portrait_url' => $character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null,
        ];
    }
}
