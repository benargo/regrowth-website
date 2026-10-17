<?php

namespace App\Data\Attendance;

use App\Enums\Presence;
use App\Models\Character;
use Spatie\LaravelData\Data;

/**
 * A character paired with its presence in a raid.
 */
final class PlayerPresenceData extends Data
{
    public function __construct(
        public readonly Character $character,
        public readonly Presence $presence,
    ) {}
}
