<?php

namespace App\Http\Integrations\Blizzard\Concerns;

use App\Http\Integrations\Concerns\CachesResponses;

/**
 * Caches Blizzard responses under the `blizzard` and `blizzard-api-response` tags.
 */
trait CachesBlizzardResponses
{
    use CachesResponses;

    /**
     * @return array<int, string>
     */
    protected function cacheTags(): array
    {
        return ['blizzard', 'blizzard-api-response'];
    }
}
