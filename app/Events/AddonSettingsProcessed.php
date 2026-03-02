<?php

namespace App\Events;

use App\Contracts\Events\PreparesRegrowthAddonData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddonSettingsProcessed implements PreparesRegrowthAddonData
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        //
    }
}
