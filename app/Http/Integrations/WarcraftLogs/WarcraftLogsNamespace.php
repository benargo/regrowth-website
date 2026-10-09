<?php

namespace App\Http\Integrations\WarcraftLogs;

use App\Enums\Concerns\HasSelectOptions;

enum WarcraftLogsNamespace: string
{
    use HasSelectOptions;

    case Anniversary = 'anniversary';
    case Classic = 'classic'; // Mists of Pandaria Classic
    case Era = 'era';
    case Forever = 'forever';
    case Retail = 'retail';
    case SeasonOfDiscovery = 'season_of_discovery';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Anniversary => 'https://fresh.warcraftlogs.com/api/v2/client',
            self::Classic => 'https://classic.warcraftlogs.com/api/v2/client',
            self::Era => 'https://vanilla.warcraftlogs.com/api/v2/client',
            self::Forever => 'https://forever.warcraftlogs.com/api/v2/client',
            self::Retail => 'https://www.warcraftlogs.com/api/v2/client',
            self::SeasonOfDiscovery => 'https://sod.warcraftlogs.com/api/v2/client',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Anniversary => 'The Burning Crusade Classic Anniversary',
            self::Classic => 'Mists of Pandaria Classic',
            self::Era => 'Classic Era',
            self::Forever => 'World of Warcraft: Forever',
            self::Retail => 'World of Warcraft',
            self::SeasonOfDiscovery => 'Season of Discovery',
        };
    }
}
