<?php

namespace App\Models\WarcraftLogs;

use App\Events\AddonSettingsProcessed;
use App\Models\TBC\Phase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuildTag extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wcl_guild_tags';

    /**
     * The attributes that are the model's default values.
     *
     * @var array
     */
    protected $attributes = [
        'count_attendance' => false,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'count_attendance' => 'boolean',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'updated' => AddonSettingsProcessed::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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
     *
     * @return BelongsTo<Phase>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'tbc_phase_id');
    }

    /**
     * Get the reports associated with the guild tag.
     *
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'guild_tag_id', 'id');
    }
}
