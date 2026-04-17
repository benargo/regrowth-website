<?php

namespace App\Models\Raids;

use App\Events\ReportCreated;
use App\Events\ReportUpdated;
use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'raid_reports';

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
        'guild_tag_id',
        'zone_id',
        'zone_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'zone_id' => 'integer',
        ];
    }

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ReportCreated::class,
        'updated' => ReportUpdated::class,
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
        return $this->belongsToMany(Character::class, 'pivot_characters_raid_reports', 'raid_report_id', 'character_id')
            ->using(CharacterReport::class)
            ->withPivot('presence', 'is_loot_councillor');
    }

    /**
     * Get the guild tag associated with this report.
     *
     * @return BelongsTo<GuildTag, $this>
     */
    public function guildTag(): BelongsTo
    {
        return $this->belongsTo(GuildTag::class, 'guild_tag_id', 'id');
    }

    /**
     * Get the reports that are linked to this report.
     */
    public function linkedReports(): BelongsToMany
    {
        return $this->belongsToMany(
            Report::class,
            'raid_report_links',
            'report_1',
            'report_2'
        )->using(ReportLink::class)->withPivot('created_by')->withTimestamps();
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
