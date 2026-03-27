<?php

namespace App\Events;

use App\Contracts\Events\SchedulesAddonExportBuild;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddonSettingsProcessed implements SchedulesAddonExportBuild
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
