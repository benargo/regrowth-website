<?php

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\HasBlizzardIcons;
use App\Enums\ItemQuality;
use App\Events\ItemSaved;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['boss_id', 'name', 'quality', 'group', 'notes'])]
#[Hidden(['wowhead_url', 'created_at', 'updated_at'])]
class Item extends Model implements Commentable, HasBlizzardIcons, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quality' => ItemQuality::class,
        ];
    }

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

    // ============ Blizzard data ============

    public function fillBlizzardData(ItemData $data): static
    {
        return $this->forceFill([
            'name' => $data->name,
            'inventoryType' => ['name' => $data->inventoryType->name],
            'itemClass' => ['name' => $data->itemClass->name],
            'itemSubclass' => ['name' => $data->itemSubclass->name],
        ]);
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
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the channel that comments on this item broadcast on.
     */
    public function commentChannel(): Channel
    {
        return new Channel("item.{$this->id}");
    }

    /**
     * Get the raids that this item drops in.
     *
     * Trash items can drop in more than one instance, so the link is a pivot
     * rather than a foreign key on the item itself.
     *
     * @return BelongsToMany<Raid, $this>
     */
    public function raids(): BelongsToMany
    {
        return $this->belongsToMany(Raid::class, 'pivot_items_raids', 'item_id', 'raid_id')
            ->withTimestamps();
    }

    /**
     * Get the priorities for this item.
     *
     * @return BelongsToMany<LootPriority, $this>
     */
    public function priorities(): BelongsToMany
    {
        return $this->belongsToMany(LootPriority::class, 'pivot_items_priorities', 'item_id', 'priority_id')
            ->using(ItemPriority::class)
            ->withPivot('weight')
            ->withTimestamps();
    }

    // ============ Trash ============

    /**
     * Constrain the query to trash items (items without a boss).
     */
    #[Scope]
    protected function trash(Builder $query): void
    {
        $query->whereNull('boss_id');
    }

    // ============ Search ============

    /**
     * Constrain the query to items whose name matches the given term, using the
     * FULLTEXT index.
     *
     * Items are created before Blizzard data fills their name, so nameless rows
     * exist in the table and must never surface as results.
     *
     * Uses whereFullText()'s boolean mode with a trailing wildcard rather than
     * the default natural language mode, which only matches whole words —
     * silently dropping partial matches like "slipper" against "Slippers".
     * Boolean mode with a `*` suffix does prefix matching instead, which is
     * what a search-as-you-type box needs.
     *
     * The term is expected to already be sanitised (see SearchRequest) — this
     * scope only decides how to match it, not how to clean it.
     */
    #[Scope]
    protected function matchingName(Builder $query, string $term): void
    {
        $boolean = implode(' ', array_map(fn (string $word): string => $word.'*', explode(' ', $term)));

        $query->whereNotNull('name')->whereFullText('name', $boolean, ['mode' => 'boolean']);
    }
}
