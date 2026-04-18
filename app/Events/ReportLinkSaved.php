<?php

namespace App\Events;

use App\Contracts\Events\FlushesAttendanceCache;
use App\Contracts\Events\FlushesReportsCache;
use App\Contracts\Events\SchedulesAddonExportBuild;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportLinkSaved implements FlushesAttendanceCache, FlushesReportsCache, SchedulesAddonExportBuild
{
    use Dispatchable, SerializesModels;
}
