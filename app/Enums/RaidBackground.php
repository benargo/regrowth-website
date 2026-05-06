<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum RaidBackground: string
{
    case KARAZHAN = 'bg-raid-karazhan';
    case GRUUL_MAGTHERIDON = 'bg-raid-gruul-magtheridon';
    case SERPENTSHRINE_CAVERN = 'bg-raid-serpentshrine-cavern';
    case TEMPEST_KEEP = 'bg-raid-tempest-keep';
    case DEFAULT = 'bg-ssctk';

    /**
     * Get the corresponding RaidBackground enum case from a given raid ID.
     */
    public static function fromRaidId(mixed $raidIds): self
    {
        if (is_iterable($raidIds)) {
            $raidIds = Arr::first($raidIds);
        }

        return match ($raidIds) {
            1 => self::KARAZHAN,
            2 => self::GRUUL_MAGTHERIDON,
            3 => self::GRUUL_MAGTHERIDON,
            4 => self::SERPENTSHRINE_CAVERN,
            5 => self::TEMPEST_KEEP,
            default => self::DEFAULT,
        };
    }
}
