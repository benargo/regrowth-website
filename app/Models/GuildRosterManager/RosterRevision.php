<?php

namespace App\Models\GuildRosterManager;

use App\Models\GameVersion;
use Database\Factories\GuildRosterManager\RosterRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A committed revision of a game version's guild roster state.
 */
#[Fillable([
    'game_version_id',
    'revision',
    'upload_id',
    'committed_at',
    'ranks',
    'ranks_fingerprint',
])]
#[Table('grm_roster_revisions')]
class RosterRevision extends Model
{
    /** @use HasFactory<RosterRevisionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'ranks' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * Get the game version the revision belongs to.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the upload that committed the revision.
     *
     * @return BelongsTo<Upload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
