<?php

namespace App\Events;

use App\Contracts\Events\FlushesAttendanceCache;
use App\Contracts\Events\FlushesReportsCache;
use App\Models\Raids\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportUpdated implements FlushesAttendanceCache, FlushesReportsCache
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Report $report) {}
}
