<?php

namespace App\Models\GuildRosterManager;

use App\Casts\AsRosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GameVersion;
use Database\Factories\GuildRosterManager\RosterEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of a game version's current guild roster state, stored as a camelCase JSON payload.
 */
#[Fillable([
    'game_version_id',
    'kind',
    'key',
    'payload',
    'fingerprint',
    'epoch',
    'revision',
])]
#[Table('grm_roster_entries')]
class RosterEntry extends Model
{
    /** @use HasFactory<RosterEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RosterEntryKind::class,
            'payload' => AsRosterEntryData::class,
            'epoch' => 'integer',
        ];
    }

    /**
     * Get the game version the entry belongs to.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
