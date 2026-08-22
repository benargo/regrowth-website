<?php

namespace App\Models;

use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['character_id', 'raid_report_id', 'presence', 'is_loot_councillor'])]
#[Table(name: 'pivot_characters_raid_reports', timestamps: false)]
#[Touches('report')]
class CharacterReport extends Pivot
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_loot_councillor' => 'boolean',
        ];
    }

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
