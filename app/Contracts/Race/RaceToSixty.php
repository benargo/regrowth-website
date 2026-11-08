<?php

namespace App\Contracts\Race;

interface RaceToSixty
{
    /**
     * The level a character must reach to place in the race.
     */
    public const int TARGET_LEVEL = 60;

    /**
     * The number of levels below the target that count as being in the pack.
     */
    public const int PACK_WINDOW = 5;

    /**
     * The hours after the bronze placement during which honourable mentions are collected.
     */
    public const int HONOURABLE_MENTION_HOURS = 48;

    /**
     * The cache key holding the time of the last race check.
     */
    public const string CHECKED_AT_CACHE_KEY = 'race-to-60:checked-at';
}
