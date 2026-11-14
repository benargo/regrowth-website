<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;

/**
 * Validates a batch of alt group rows.
 */
class StoreAltGroupBatchRequest extends StoreSyncBatchRequest
{
    public function kind(): RosterEntryKind
    {
        return RosterEntryKind::AltGroup;
    }

    protected function rowRules(): array
    {
        return [
            'id' => ['required', 'string', 'max:32', 'distinct'],
            'main' => ['nullable', 'string', 'max:64'],
            'members' => ['present', 'array', 'max:50'],
            'members.*' => self::GUID_RULES,
            'epoch' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
