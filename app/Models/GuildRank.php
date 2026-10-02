<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

#[UsePolicy(DatasetPolicy::class)]
#[Fillable(['sort_order', 'name', 'count_attendance'])]
class GuildRank extends Model implements DatasetModel, Sortable
{
    use HasFactory, SortableTrait;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'count_attendance' => true,
    ];

    // ============ Sorting ============

    /**
     * Assign the next sort_order, starting from 0 when no ranks exist.
     */
    public function setHighestOrderNumber(): void
    {
        $this->sort_order = $this->buildSortQuery()->exists()
            ? $this->getHighestOrderNumber() + 1
            : 0;
    }

    /**
     * Determine whether sort_order should be auto-assigned on create.
     */
    public function shouldSortWhenCreating(): bool
    {
        return $this->sort_order === null;
    }

    /**
     * Scope sorting to ranks within the same game version.
     */
    public function buildSortQuery(): Builder
    {
        return static::query()->where('game_version_id', $this->game_version_id);
    }

    // ============ Casting ============

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'count_attendance' => 'boolean',
        ];
    }

    // ============ Custom attributes ============

    /**
     * Set the name attribute to be title-cased.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::ucwords(Str::lower($value)),
        );
    }

    // ============ Relationships ============

    /**
     * Get the game version whose guild has this rank.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
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
