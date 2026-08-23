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

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        );
    }

    // ============ Relationships ============

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<PlayableSpecialization, $this>
     */
    public function specializations(): HasMany
    {
        return $this->hasMany(PlayableSpecialization::class);
    }

    /**
     * @return HasMany<LootPriority, $this>
     */
    public function lootPriorities(): HasMany
    {
        return $this->hasMany(LootPriority::class);
    }
}
