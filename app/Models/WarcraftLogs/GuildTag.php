<?php

namespace App\Models\WarcraftLogs;

use App\Models\Report;
use App\Observers\WarcraftLogs\GuildTagObserver;
use App\Policies\WarcraftLogsGuildPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([GuildTagObserver::class])]
#[UsePolicy(WarcraftLogsGuildPolicy::class)]
#[Fillable(['id', 'name', 'count_attendance', 'warcraft_logs_guild_id'])]
#[Table('warcraft_logs_guild_tags')]
class GuildTag extends Model
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
            'warcraft_logs_guild_id' => 'integer',
        ];
    }

    /**
     * Get the Warcraft Logs guild the tag belongs to.
     *
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class, 'warcraft_logs_guild_id');
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
