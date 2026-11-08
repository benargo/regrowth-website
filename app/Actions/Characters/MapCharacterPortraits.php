<?php

namespace App\Actions\Characters;

use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lorisleiva\Actions\Concerns\AsAction;

class MapCharacterPortraits
{
    use AsAction;

    /**
     * Map each character in the query to its portrait URL, or null, keyed by character ID.
     *
     * @param  Builder<Character>|HasMany<Character, *>  $characters
     */
    public function handle(Builder|HasMany $characters): object
    {
        return (object) $characters
            ->with('media')
            ->get()
            ->mapWithKeys(fn (Character $character): array => [
                $character->id => $character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null,
            ])
            ->all();
    }
}
