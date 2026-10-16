<?php

namespace App\Enums;

/**
 * A character's presence in a raid report, as stored in
 * `pivot_characters_raid_reports.presence`.
 */
enum Presence: int
{
    case Absent = 0;
    case Present = 1;
    case Benched = 2;

    /**
     * Rank used to pick one presence across linked reports: Present 2, Benched 1, Absent 0.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Present => 2,
            self::Benched => 1,
            self::Absent => 0,
        };
    }

    /**
     * Whether this presence counts as attending the raid.
     */
    public function isAttended(): bool
    {
        return $this !== self::Absent;
    }
}
