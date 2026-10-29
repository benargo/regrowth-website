<?php

namespace App\Http\Integrations\RaidHelper\Concerns;

use App\Http\Integrations\Concerns\CachesResponses;

/**
 * Caches Raid Helper responses under the `raidhelper` and `raidhelper-api-response` tags.
 */
trait CachesRaidHelperResponses
{
    use CachesResponses;

    /**
     * @return array<int, string>
     */
    protected function cacheTags(): array
    {
        return ['raidhelper', 'raidhelper-api-response'];
    }
}
