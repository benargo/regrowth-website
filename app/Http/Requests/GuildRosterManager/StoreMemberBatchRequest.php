<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\Concerns\ValidatesCharacterRows;

/**
 * Validates a batch of current member rows.
 */
class StoreMemberBatchRequest extends StoreSyncBatchRequest
{
    use ValidatesCharacterRows;

    public function kind(): RosterEntryKind
    {
        return RosterEntryKind::Member;
    }

    protected function rowRules(): array
    {
        return [...$this->characterRules(), 'altGroup' => ['nullable', 'string', 'max:32']];
    }
}
