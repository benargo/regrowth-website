<?php

namespace App\Models;

use App\Contracts\Models\DatasetModel;
use App\Models\Concerns\SortsExplicitlyOnCreate;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;

#[UsePolicy(DatasetPolicy::class)]
#[Fillable(['sort_order', 'name', 'count_attendance'])]
class GuildRank extends Model implements DatasetModel, Sortable
{
    use HasFactory, SortsExplicitlyOnCreate;

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
     * GuildRank sort_order is 0-based, unlike the other sortable models in this app.
     *
     * The value doubles as Blizzard's numeric guild-rank index (see FetchGuildRoster,
     * AddonController, GuildRosterMemberCollection), which the WoW API defines as
     * 0-based. Keeping them identical avoids an offset translation at every call site,
     * so the first rank in an empty table must be assigned 0 rather than the package
     * default of 1.
     */
    public function setHighestOrderNumber(): void
    {
        $this->sort_order = $this->buildSortQuery()->exists()
            ? $this->getHighestOrderNumber() + 1
            : 0;
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
