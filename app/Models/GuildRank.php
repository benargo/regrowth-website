<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GuildRank extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'guild_ranks';

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'count_attendance' => true,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'position' => 'integer',
        'count_attendance' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'position',
        'name',
        'count_attendance',
    ];

    /**
     * Set the name attribute to be title-cased.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::ucwords(Str::lower($value)),
        );
    }

    /**
     * Get the characters for the guild rank.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'rank_id');
    }

    /**
     * Get the main characters for the guild rank.
     */
    public function mainCharacters(): HasMany
    {
        return $this->characters()->where('is_main', true);
    }
}
