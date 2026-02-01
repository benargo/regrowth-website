<?php

namespace App\Models\WarcraftLogs;

use App\Models\TBC\Phase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildTag extends Model
{
    /** @use HasFactory<\Database\Factories\WarcraftLogs\GuildTagFactory> */
    use HasFactory;

    protected $table = 'wcl_guild_tags';

    protected $attributes = [
        'count_attendance' => false,
    ];

    protected $fillable = [
        'id',
        'name',
        'count_attendance',
        'tbc_phase_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count_attendance' => 'boolean',
        ];
    }

    /**
     * Get the TBC phase associated with the guild tag.
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'tbc_phase_id');
    }
}
