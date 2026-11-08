<?php

namespace App\Models;

use App\Enums\Medal;
use Database\Factories\RacePlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_version_id', 'character_id', 'position', 'reached_at'])]
class RacePlacement extends Model
{
    /** @use HasFactory<RacePlacementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reached_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<Medal|null, never>
     */
    protected function medal(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Medal => $this->position !== null ? Medal::fromPosition($this->position) : null,
        );
    }

    // ========== Relationships ============

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }
}
