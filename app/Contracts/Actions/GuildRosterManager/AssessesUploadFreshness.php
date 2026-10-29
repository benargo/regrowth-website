<?php

namespace App\Contracts\Actions\GuildRosterManager;

/**
 * An action that judges how far a game version's latest GRM upload can be
 * trusted, sharing one definition of "stale" and "outdated".
 */
interface AssessesUploadFreshness
{
    /**
     * The difference between the upload's and the live roster's counts at which
     * an upload counts as stale.
     */
    public const int STALE_THRESHOLD = 3;

    /**
     * The age in days at which an upload counts as outdated.
     */
    public const int OUTDATED_AFTER_DAYS = 7;
}
