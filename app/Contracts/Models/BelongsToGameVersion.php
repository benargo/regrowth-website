<?php

namespace App\Contracts\Models;

use App\Models\GameVersion;

/**
 * A record that belongs to one game version, or to none.
 */
interface BelongsToGameVersion
{
    /**
     * The game version this record belongs to, or null when it has none.
     */
    public function owningGameVersion(): ?GameVersion;
}
