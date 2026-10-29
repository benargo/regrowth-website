<?php

namespace App\Contracts\Models;

/**
 * A model whose change event is dispatched when its media is added, cleared or converted.
 */
interface DispatchesChangesForMedia
{
    /**
     * The event dispatched with this model when its media changes.
     *
     * @return class-string
     */
    public function mediaChangedEvent(): string;
}
