<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\PlayableSpecRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['playable_class_id', 'role', 'name'])]
#[Hidden(['created_at', 'updated_at'])]
class PlayableSpecialization extends Model implements HasBlizzardIcons, HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlayableSpecRole::class,
        ];
    }

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
