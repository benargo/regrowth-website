<?php

namespace App\Enums\GuildRosterManager;

enum RowReason: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Renamed = 'renamed';
    case MainChanged = 'main_changed';
    case Detached = 'detached';
    case Left = 'left';
    case Rejoined = 'rejoined';
    case BelowMinimumLevel = 'below_minimum_level';
    case NotTracked = 'not_tracked';
    case NotConfirmedInGuild = 'not_confirmed_in_guild';
    case PlaceholderName = 'placeholder_name';
    case UnknownClass = 'unknown_class';
    case UnknownRace = 'unknown_race';
    case UnknownRank = 'unknown_rank';
    case NameClash = 'name_clash';
    case Error = 'error';

    /**
     * Whether the reason describes an anticipated outcome rather than a problem.
     */
    public function isExpected(): bool
    {
        return match ($this) {
            self::BelowMinimumLevel,
            self::NotTracked,
            self::NotConfirmedInGuild,
            self::PlaceholderName => true,
            default => false,
        };
    }
}
