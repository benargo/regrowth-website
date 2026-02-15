<?php

namespace App\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\LootBiasPrioritiesProcessed;
use App\Jobs\BuildAddonDataFile as Job;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class BuildAddonDataFile implements ShouldBeUnique, ShouldQueue
{
    /**
     * The number of seconds after which the listener's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 600; // 10 minutes

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AddonSettingsProcessed|LootBiasPrioritiesProcessed $event): void
    {
        Job::dispatch();
    }
}
