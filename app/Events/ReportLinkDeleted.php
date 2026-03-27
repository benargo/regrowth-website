<?php

namespace App\Events;

use App\Contracts\Events\SchedulesAddonExportBuild;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportLinkDeleted implements SchedulesAddonExportBuild
{
    use Dispatchable, SerializesModels;
}
