<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\Concerns\ValidatesCharacterRows;

/**
 * Validates a batch of leaver rows.
 */
class StoreLeaverBatchRequest extends StoreSyncBatchRequest
{
    use ValidatesCharacterRows;

    public function kind(): RosterEntryKind
    {
        return RosterEntryKind::Leaver;
    }

    protected function rowRules(): array
    {
        return $this->characterRules();
    }
}
