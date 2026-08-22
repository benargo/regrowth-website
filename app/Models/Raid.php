<?php

namespace App\Models;

use App\Casts\AsBinaryColor;
use App\Enums\RaidBackground;
use App\Models\Concerns\FlushesRaidingCacheOnSave;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

#[Appends(['slug'])]
#[Fillable(['name', 'difficulty', 'background_css_class', 'color', 'phase_id', 'max_players', 'max_loot_councillors'])]
class Raid extends Model
{
    use FlushesRaidingCacheOnSave;
    use HasFactory;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'background_css_class' => RaidBackground::class,
            'color' => AsBinaryColor::class,
            'max_players' => 'integer',
            'max_loot_councillors' => 'integer',
        ];
    }

    // ============ Custom attributes ============

    /**
     * Get the max_groups attribute for the raid, which is the maximum number of groups that can be formed based on the max_players attribute.
     */
    protected function maxGroups(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ceil($this->max_players / 5),
        );
    }

    /**
     * Get the slug attribute for the raid, which is a URL-friendly version of the raid name.
     *
     * This is not stored in the database, but is generated on the fly when accessed. It is used for creating SEO-friendly URLs for raids.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        );
    }

    // ============ Dataset relationships ============

    /**
     * Get the bosses in this raid.
     *
     * @return HasMany<Boss, $this>
     */
    public function bosses(): HasMany
    {
        return $this->hasMany(Boss::class);
    }

    /**
     * Get the phase that this raid belongs to.
     *
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    // ============ Event relationships ============

    /**
     * Get the events that are associated with this raid.
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'pivot_events_raids', 'raid_id', 'event_id')
            ->withTimestamps();
    }

    // ========== Loot bias relationships ==========

    /**
     * Get the items that drop in this raid.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'pivot_items_raids', 'raid_id', 'item_id')
            ->withTimestamps();
    }

    /**
     * Get the trash items that drop in this raid (items without a boss).
     *
     * @return BelongsToMany<Item, $this>
     */
    public function trashItems(): BelongsToMany
    {
        return $this->items()->trash();
    }

    /**
     * Get the comments on the items that drop in this raid (including trash drops).
     *
     * A HasManyThrough cannot hop across a pivot table, so the item ids are
     * resolved with a subquery over the pivot instead. The relation is built
     * with Relation::noConstraints() because hasMany() would otherwise add
     * its own `commentable_id = raids.id` constraint — comparing the item id
     * stored in the polymorphic column against the raid's own key, which is
     * meaningless here. The whereIn() subquery is the only constraint that
     * should apply.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return Relation::noConstraints(fn () => $this->hasMany(Comment::class, 'commentable_id')
            ->where('commentable_type', Item::class)
            ->whereIn('commentable_id', $this->items()->select('items.id')));
    }
}
