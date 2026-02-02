<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    /**
     * Get the guild rank associated with the character.
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(GuildRank::class, 'rank_id');
    }

    /**
     * Get the main character from the linked characters.
     */
    protected function mainCharacter(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->linkedCharacters()->where('is_main', true)->first(),
        );
    }

    public function linkedCharacters(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'character_links', 'linked_character_id', 'character_id');
    }
}
