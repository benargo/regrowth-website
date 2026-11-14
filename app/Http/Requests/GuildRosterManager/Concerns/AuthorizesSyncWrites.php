<?php

namespace App\Http\Requests\GuildRosterManager\Concerns;

/**
 * For form requests that write to a desktop sync bound to the `{sync}` route parameter.
 */
trait AuthorizesSyncWrites
{
    /**
     * Determine whether the user may write to the sync from the requesting desktop client.
     */
    protected function canWriteSync(): bool
    {
        return $this->user()->can('writeSync', [$this->route('sync'), $this->attributes->get('desktopClient')]);
    }
}
