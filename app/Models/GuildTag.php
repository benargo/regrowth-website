<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Models\Raids\Report;
use App\Observers\GuildTagObserver;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([GuildTagObserver::class])]
#[UsePolicy(DatasetPolicy::class)]
#[Fillable(['id', 'name', 'count_attendance', 'tbc_phase_id'])]
#[Table('wcl_guild_tags')]
class GuildTag extends Model implements DatasetModel
{
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'count_attendance' => false,
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
