<?php

namespace App\Models;

use App\Observers\ReportLinkObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy([ReportLinkObserver::class])]
#[Table('pivot_report_links')]
#[Touches(['report1', 'report2'])]
class ReportLink extends Pivot
{
    /**
     * Get the user who created this link.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the first report in the link.
     */
    public function report1(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_1');
    }

    /**
     * Get the second report in the link.
     */
    public function report2(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_2');
    }
}
