<?php

namespace App\Models;

use App\Models\WarcraftLogs\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CharacterReport extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pivot_characters_wcl_reports';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * All of the relationships to be touched.
     *
     * @var array<int, string>
     */
    protected $touches = ['report'];

    /**
     * Get the report this pivot entry belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'wcl_report_code', 'code');
    }

    /**
     * Get the character this pivot entry belongs to.
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
