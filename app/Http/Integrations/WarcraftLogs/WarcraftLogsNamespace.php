<?php

namespace App\Http\Integrations\WarcraftLogs;

enum WarcraftLogsNamespace: string
{
    case ANNIVERSARY = 'anniversary';
    case CLASSIC = 'classic'; // Mists of Pandaria Classic
    case ERA = 'era';
    case FOREVER = 'forever';
    case RETAIL = 'retail';
    case SEASON_OF_DISCOVERY = 'season_of_discovery';

    public function baseUrl(): string
    {
        return match ($this) {
            self::ANNIVERSARY => 'https://fresh.warcraftlogs.com/api/v2/client',
            self::CLASSIC => 'https://classic.warcraftlogs.com/api/v2/client',
            self::ERA => 'https://vanilla.warcraftlogs.com/api/v2/client',
            self::FOREVER => 'https://forever.warcraftlogs.com/api/v2/client',
            self::RETAIL => 'https://www.warcraftlogs.com/api/v2/client',
            self::SEASON_OF_DISCOVERY => 'https://sod.warcraftlogs.com/api/v2/client',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ANNIVERSARY => 'The Burning Crusade Classic Anniversary',
            self::CLASSIC => 'Mists of Pandaria Classic',
            self::ERA => 'Classic Era',
            self::FOREVER => 'World of Warcraft: Forever',
            self::RETAIL => 'World of Warcraft',
            self::SEASON_OF_DISCOVERY => 'Season of Discovery',
        };
    }
}
