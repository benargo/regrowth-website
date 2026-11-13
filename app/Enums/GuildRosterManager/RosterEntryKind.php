<?php

namespace App\Enums\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;

enum RosterEntryKind: string
{
    case Member = 'member';
    case Leaver = 'leaver';
    case AltGroup = 'alt_group';
    case Ban = 'ban';

    /**
     * Get the Data class that represents a row of this kind.
     *
     * @return class-string
     */
    public function dataClass(): string
    {
        return match ($this) {
            self::Member => MemberData::class,
            self::Leaver => LeaverData::class,
            self::AltGroup => AltGroupData::class,
            self::Ban => BanData::class,
        };
    }

    /**
     * Get the payload attribute that identifies a row of this kind.
     */
    public function keyAttribute(): string
    {
        return $this === self::AltGroup ? 'id' : 'guid';
    }

    /**
     * Get the maximum number of rows a sync may stage for this kind, or null when unlimited.
     */
    public function maxRows(): ?int
    {
        return match ($this) {
            self::Member => 1000,
            self::Leaver => 5000,
            self::AltGroup => 1000,
            self::Ban => null,
        };
    }

    /**
     * Get the name of the fingerprint map that holds rows of this kind.
     */
    public function fingerprintGroup(): string
    {
        return match ($this) {
            self::Member => 'members',
            self::Leaver => 'leavers',
            self::AltGroup => 'altGroups',
            self::Ban => 'bans',
        };
    }
}
