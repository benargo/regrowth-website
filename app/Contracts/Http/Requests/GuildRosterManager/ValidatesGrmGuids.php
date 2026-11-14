<?php

namespace App\Contracts\Http\Requests\GuildRosterManager;

/**
 * For requests that validate Guild Roster Manager player GUIDs.
 */
interface ValidatesGrmGuids
{
    /**
     * The validation rules for a GRM player GUID, such as `Player-4395-0A1B2C3D`.
     *
     * @var list<string>
     */
    public const array GUID_RULES = ['string', 'max:40', 'regex:/^Player-\d+-[0-9A-F]+$/'];
}
