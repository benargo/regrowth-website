<?php

namespace App\Models\Raids;

use App\Events\ReportLinkDeleted;
use App\Events\ReportLinkSaved;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReportLink extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'raid_report_links';

    /**
     * All of the relationships to be touched.
     *
     * @var array<int, string>
     */
    protected $touches = ['report1', 'report2'];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saved' => ReportLinkSaved::class,
        'deleted' => ReportLinkDeleted::class,
    ];

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
