<?php

namespace App\Http\Integrations\Blizzard;

enum GameVersion: string
{
    case Anniversary = 'Burning Crusade Classic (Anniversary)';
    case Classic = 'Mists of Pandaria Classic (Progression)';
    case Era = 'World of Warcraft Classic (Era)';
    case Retail = 'World of Warcraft';

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new \ValueError("\"$name\" is not a valid name for enum ".self::class);
    }

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
