<?php

namespace App\Models;

use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CharacterReport extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pivot_characters_raid_reports';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'character_id',
        'raid_report_id',
        'presence',
        'is_loot_councillor',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_loot_councillor' => 'boolean',
    ];

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
        return $this->belongsTo(Report::class, 'raid_report_id');
    }

    /**
     * Get the character this pivot entry belongs to.
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
