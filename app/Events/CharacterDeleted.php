<?php

namespace App\Events;

use App\Contracts\Events\SchedulesAddonExportBuild;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterDeleted implements SchedulesAddonExportBuild
{
    use Dispatchable, SerializesModels;
}
