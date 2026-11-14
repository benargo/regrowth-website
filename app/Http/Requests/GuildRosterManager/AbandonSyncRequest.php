<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Http\Requests\GuildRosterManager\Concerns\AuthorizesSyncWrites;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorises abandoning a desktop sync.
 */
class AbandonSyncRequest extends FormRequest
{
    use AuthorizesSyncWrites;

    /**
     * Determine whether the user may write to the sync from the requesting desktop client.
     */
    public function authorize(): bool
    {
        return $this->canWriteSync();
    }
}
