<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Models\Raids\Report;
use App\Observers\GuildTagObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([GuildTagObserver::class])]
class GuildTag extends Model implements DatasetModel
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
