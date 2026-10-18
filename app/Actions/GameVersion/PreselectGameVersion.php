<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Picks the game version a version-independent page opens on: the requested
 * slug when selectable, otherwise the site's current version when selectable,
 * otherwise the first selectable version.
 */
class PreselectGameVersion
{
    use AsAction;

    /**
     * @param  Collection<int, GameVersion>  $selectable
     */
    public function handle(Collection $selectable, ?string $requestedSlug): ?GameVersion
    {
        return ($requestedSlug !== null ? $selectable->firstWhere('slug', $requestedSlug) : null)
            ?? (($current = ResolveCurrentGameVersion::run()) !== null ? $selectable->firstWhere('id', $current->id) : null)
            ?? $selectable->first();
    }
}
