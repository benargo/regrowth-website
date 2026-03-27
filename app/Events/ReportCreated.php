<?php

namespace App\Events;

use App\Models\WarcraftLogs\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public readonly Report $report) {}
}
