<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists every game version, newest release first, with media loaded.
 * The result is memoised for the duration of the request.
 */
class ListSelectableGameVersions
{
    use AsAction;

    /**
     * @return Collection<int, GameVersion>
     */
    public function handle(): Collection
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::class)) {
            $attributes->set(self::class, GameVersion::with('media')
                ->orderByDesc('release_date')
                ->orderBy('id')
                ->get());
        }

        return $attributes->get(self::class);
    }
}
