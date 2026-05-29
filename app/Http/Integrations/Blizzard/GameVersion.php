<?php

namespace App\Http\Integrations\Blizzard;

enum GameVersion: string
{
    case Anniversary = 'Burning Crusade Classic (Anniversary)';
    case Classic = 'Mists of Pandaria Classic (Progression)';
    case Era = 'World of Warcraft Classic (Era)';
    case Retail = 'World of Warcraft';

    /**
     * Returns the namespace component for this game version, which is used in Blizzard API endpoints.
     */
    public function namespaceComponent(): string
    {
        return match ($this) {
            self::Anniversary => '-classicann',
            self::Classic => '-classic',
            self::Era => '-classic1x',
            self::Retail => '',
        };
    }
}
