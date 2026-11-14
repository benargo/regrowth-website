<?php

namespace App\Models\GuildRosterManager;

use App\Models\Character;
use App\Models\GameVersion;
use App\Models\User;
use App\Policies\BanPolicy;
use Database\Factories\GuildRosterManager\BanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Guild Roster Manager ban log entry, kept for the life of the guild.
 */
#[Fillable([
    'game_version_id',
    'grm_guid',
    'name',
    'character_id',
    'user_id',
    'banned_by',
    'banned_at',
    'reason',
    'unbanned_at',
])]
#[Hidden(['reason'])]
#[Table('grm_bans')]
#[UsePolicy(BanPolicy::class)]
class Ban extends Model
{
    /** @use HasFactory<BanFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
            'unbanned_at' => 'datetime',
        ];
    }

    // ============ Relationships ============

    /**
     * Get the character the ban was matched to, if any.
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Get the game version the ban belongs to.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the user the ban was matched to, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============ Scopes ============

    /**
     * Limit the query to bans that have not been lifted.
     *
     * @param  Builder<Ban>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('unbanned_at');
    }
}
