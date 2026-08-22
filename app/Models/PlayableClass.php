<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['id', 'name'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class PlayableClass extends Model implements HasBlizzardIcons, HasMedia
{
    use HasFactory, InteractsWithMedia;

    // ============ Custom attributes ============

    /**
     * Generates a URL-friendly slug from the class name for use in frontend routing or CSS classes.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        );
    }

    // ============ Relationships ============

    /**
     * A playable class can have many characters.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * A playable class can have many specializations.
     */
    public function specializations(): HasMany
    {
        return $this->hasMany(PlayableSpecialization::class);
    }
}
