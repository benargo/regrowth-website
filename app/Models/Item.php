<?php

namespace App\Models;

use App\Contracts\Models\HasBlizzardIcons;
use App\Events\ItemSaved;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Item extends Model implements HasBlizzardIcons, HasMedia
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'raid_id',
        'boss_id',
        'name',
        'group',
        'notes',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'saved' => ItemSaved::class,
    ];

    // ============ Custom attributes ============

    /**
     * Get the slug for this item based on its name.
     */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->name ?? '');
    }

    /**
     * Get the Wowhead URL for this item.
     */
    public function getWowheadUrlAttribute(): string
    {
        $base = "https://www.wowhead.com/tbc/item={$this->id}";

        return $this->name ? $base.'/'.$this->slug : $base;
    }

    // ============ Media ============

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('blizzard_icons')->singleFile();
    }

    // ============ Relationships ============

    /**
     * Get the boss that this item drops from.
     *
     * @return BelongsTo<Boss, $this>
     */
    public function boss(): BelongsTo
    {
        return $this->belongsTo(Boss::class);
    }

    /**
     * Get the comments for this item.
     *
     * @return HasMany<LootCouncil\Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(LootCouncil\Comment::class);
    }

    /**
     * Get the raid that this item drops from.
     *
     * @return BelongsTo<Raid, $this>
     */
    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }

    /**
     * Get the priorities for this item.
     *
     * @return BelongsToMany<LootCouncil\Priority, $this>
     */
    public function priorities(): BelongsToMany
    {
        return $this->belongsToMany(LootCouncil\Priority::class, 'lootcouncil_item_priorities', 'item_id', 'priority_id')
            ->using(LootCouncil\ItemPriority::class)
            ->withPivot('weight')
            ->withTimestamps();
    }
}
