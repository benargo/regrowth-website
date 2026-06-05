<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\CharacterRole;
use Database\Factories\CharacterSpecialisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CharacterSpecialisation extends Model implements HasBlizzardIcons, HasMedia
{
    /** use HasFactory<CharacterSpecialisationFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['playable_class_id', 'role', 'name'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = ['role' => CharacterRole::class];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string, string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    // ========== Relationships ============

    /**
     * Get the characters that have this specialisation.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'specialisation_id');
    }

    /**
     * Get the playable class that this specialisation belongs to.
     */
    public function playableClass(): BelongsTo
    {
        return $this->belongsTo(PlayableClass::class);
    }
}
