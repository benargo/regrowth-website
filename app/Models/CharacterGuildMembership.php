<?php

namespace App\Models;

use Database\Factories\CharacterGuildMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period during which a character was a member of the guild.
 */
#[Fillable(['character_id', 'joined_on', 'left_on', 'verified'])]
class CharacterGuildMembership extends Model
{
    /** @use HasFactory<CharacterGuildMembershipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
            'verified' => 'boolean',
        ];
    }

    // ============ Relationships ============

    /**
     * Get the character the membership period belongs to.
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
