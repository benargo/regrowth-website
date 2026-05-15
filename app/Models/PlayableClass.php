<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PlayableClass extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * The primary key is not auto-incrementing, as the IDs correspond to Blizzard's predefined class IDs.
     */
    public $incrementing = false;

    /**
     * This model does not have timestamps, as the data is static and managed manually.
     */
    public $timestamps = false;

    // ============ Custom attributes ============

    /**
     * Generates a URL-friendly slug from the class name for use in frontend routing or CSS classes.
     *
     * @return Attribute<string>
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
     *
     * @return HasMany<Character>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }
}
