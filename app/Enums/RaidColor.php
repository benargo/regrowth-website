<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum RaidColor: int
{
    case KARAZHAN = 0x8B7ED0;
    case GRUUL_MAGTHERIDON = 0x9B443E;
    case SERPENTSHRINE_CAVERN = 0x226E73;
    case TEMPEST_KEEP = 0xAE47EB;
    case DEFAULT = 0x768946;

    /**
     * Get the corresponding RaidColor enum case from a given raid ID.
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
