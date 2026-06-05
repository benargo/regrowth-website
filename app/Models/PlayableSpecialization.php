<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\PlayableSpecRole;
use Database\Factories\PlayableSpecializationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PlayableSpecialization extends Model implements HasBlizzardIcons, HasMedia
{
    /** use HasFactory<PlayableSpecializationFactory> */
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
    protected $casts = ['role' => PlayableSpecRole::class];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string, string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    // ========== Relationships ============

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'pivot_character_specializations', 'playable_specialization_id', 'character_id')
            ->withPivot('is_raid_spec')
            ->withTimestamps();
    }

    public function playableClass(): BelongsTo
    {
        return $this->belongsTo(PlayableClass::class);
    }
}
