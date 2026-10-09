<?php

namespace App\Models;

use App\Events\ReportCreated;
use App\Events\ReportUpdated;
use App\Http\Resources\ReportCollection;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseResourceCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'title', 'start_time', 'end_time', 'guild_tag_id', 'zone_id', 'warcraft_logs_guild_id'])]
#[Hidden(['created_at', 'updated_at', 'zone_id'])]
#[Table(keyType: 'string', incrementing: false)]
#[UseResourceCollection(ReportCollection::class)]
class Report extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => ReportCreated::class,
        'updated' => ReportUpdated::class,
    ];

    /**
     * Derive the game version and phase on every save.
     */
    protected static function booted(): void
    {
        static::saving(function (Report $report): void {
            $report->game_version_id = $report->deriveGameVersionId();
            $report->phase_id = $report->derivePhaseId();
        });
    }

    /**
     * The guild's latest game version released by the report's start time.
     */
    private function deriveGameVersionId(): ?int
    {
        if ($this->warcraft_logs_guild_id === null || $this->start_time === null) {
            return null;
        }

        return GameVersion::where('warcraft_logs_guild_id', $this->warcraft_logs_guild_id)
            ->released($this->start_time)
            ->orderByDesc('release_date')
            ->orderBy('id')
            ->value('id');
    }

    /**
     * The game version's latest phase started by the report's start time.
     */
    private function derivePhaseId(): ?int
    {
        if ($this->game_version_id === null) {
            return null;
        }

        return Phase::where('game_version_id', $this->game_version_id)
            ->whereNotNull('start_date')
            ->where('start_date', '<=', $this->start_time)
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->value('id');
    }

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
        ];
    }

    // ============ Custom attributes ============

    /**
     * Get the duration of the report in seconds.
     */
    public function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_time->diffInSeconds($this->end_time),
        );
    }

    // ============ Relations ============

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
     * Get the game version of this report, derived from its guild and start time.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the Warcraft Logs guild this report was fetched from.
     *
     * @return BelongsTo<Guild, $this>
     */
    public function warcraftLogsGuild(): BelongsTo
    {
        return $this->belongsTo(Guild::class, 'warcraft_logs_guild_id');
    }

    /**
     * Get the phase of this report, derived from its guild and start time.
     *
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * Get the reports that are linked to this report.
     */
    public function linkedReports(): BelongsToMany
    {
        return $this->belongsToMany(
            Report::class,
            'pivot_report_links',
            'report_1',
            'report_2'
        )->using(ReportLink::class)->withPivot('created_by')->withTimestamps();
    }

    /**
     * Get the zone associated with this report.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class)->withDefault([
            'id' => 0,
            'name' => 'No zone',
            'difficulties' => [],
        ]);
    }

    /**
     * Get the expansion associated with this report through the zone relationship.
     */
    public function expansion(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->zone?->expansion,
        );
    }
}
