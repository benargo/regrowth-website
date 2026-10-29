<?php

namespace App\Http\Integrations\Discord\Concerns;

use App\Http\Integrations\Concerns\CachesResponses;

/**
 * Caches Discord responses under the `discord` and `discord-api-response` tags.
 */
trait CachesDiscordResponses
{
    use CachesResponses;

    /**
     * @return array<int, string>
     */
    protected function cacheTags(): array
    {
        return ['discord', 'discord-api-response'];
    }
}
