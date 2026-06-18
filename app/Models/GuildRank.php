<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['position', 'name', 'count_attendance'])]
#[Hidden(['created_at', 'updated_at'])]
#[UsePolicy(DatasetPolicy::class)]
class GuildRank extends Model implements DatasetModel
{
    use HasFactory;

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
