<?php

namespace App\Models\TBC;

use App\Events\AddonSettingsProcessed;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Phase extends Model
{
    /** @use HasFactory<\Database\Factories\TBC\PhaseFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbc_phases';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
        ];
    }

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
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'description',
        'start_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Get the phase number attribute.
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return fmod($value, 1) === 0.0 ? (int) $value : $value;
            },
        );
    }

    /**
     * Get the raids that belong to this phase.
     */
    public function raids(): HasMany
    {
        return $this->hasMany(Raid::class);
    }

    /**
     * Get the bosses that belong to this phase through its raids.
     */
    public function bosses(): HasManyThrough
    {
        return $this->hasManyThrough(Boss::class, Raid::class);
    }

    /**
     * Get the Warcraft Logs guild tags associated with this phase.
     */
    public function guildTags(): HasMany
    {
        return $this->hasMany(GuildTag::class, 'tbc_phase_id');
    }

    /**
     * Determine if the phase has started.
     */
    public function hasStarted(): bool
    {
        return $this->start_date?->isPast() ?? false;
    }
}
