<?php

namespace App\Actions\GuildRosterManager;

use Carbon\CarbonInterface;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Decides whether an evidence time is later than the stored one. A null stored time is older than
 * any evidence, and null evidence is never newer.
 */
class IsNewerEvidence
{
    use AsAction;

    public function handle(?CarbonInterface $evidenceAt, ?CarbonInterface $storedAt): bool
    {
        if ($evidenceAt === null) {
            return false;
        }

        return $storedAt === null || $evidenceAt->greaterThan($storedAt);
    }
}
