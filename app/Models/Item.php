<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\ItemQuality;
use App\Events\ItemSaved;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['raid_id', 'boss_id', 'name', 'quality', 'group', 'notes'])]
#[Hidden(['wowhead_url', 'created_at', 'updated_at'])]
class Item extends Model implements HasBlizzardIcons, HasMedia
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quality' => ItemQuality::class,
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

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name ?? ''),
        );
    }

    protected function wowheadUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $base = "https://www.wowhead.com/tbc/item={$this->id}";

                return $this->name ? "{$base}/{$this->slug}" : $base;
            },
        );
    }

    // ============ Media ============

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
