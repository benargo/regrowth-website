<?php

namespace App\Models\WarcraftLogs;

use App\Models\Character;
use App\Services\WarcraftLogs\Data\Zone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wcl_reports';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'code';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'start_time',
        'end_time',
        'zone_id',
        'zone_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'zone_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'zone_id',
        'zone_name',
    ];

    /**
     * Get the characters that participated in this report.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'pivot_characters_wcl_reports', 'wcl_report_code', 'character_id')
            ->withPivot('presence');
    }

    /**
     * Get the zone attribute as a Zone object.
     */
    public function zone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->zone_id !== null
                ? Zone::fromArray(['id' => $this->zone_id, 'name' => $this->zone_name])
                : null,
        );
    }
}
