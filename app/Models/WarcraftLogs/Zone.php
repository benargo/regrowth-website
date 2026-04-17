<?php

namespace App\Models\WarcraftLogs;

use App\Casts\AsDifficultyCollection;
use App\Casts\AsExpansion;
use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wcl_zones';

    /**
     * The attributes that are the model's default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_frozen' => false,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $fillable = [
        'id',
        'name',
        'difficulties',
        'expansion',
        'is_frozen',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'difficulties' => AsDifficultyCollection::class,
        'expansion' => AsExpansion::class,
        'is_frozen' => 'boolean',
    ];

    /**
     * Get the reports for the zone.
     *
     * @return HasMany<Report>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
