<?php

namespace App\Models\LootCouncil;

use App\Casts\ItemMediaCast;
use App\Events\ItemSaved;
use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Database\Factories\LootCouncil\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lootcouncil_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'raid_id',
        'boss_id',
        'name',
        'icon',
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon' => ItemMediaCast::class,
        ];
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
     * Get the boss that this item drops from.
     *
     * @return BelongsTo<Boss, $this>
     */
    public function boss(): BelongsTo
    {
        return $this->belongsTo(Boss::class);
    }

    /**
     * Get the priorities for this item.
     *
     * @return BelongsToMany<Priority, $this>
     */
    public function priorities(): BelongsToMany
    {
        return $this->belongsToMany(Priority::class, 'lootcouncil_item_priorities', 'item_id', 'priority_id')
            ->using(ItemPriority::class)
            ->withPivot('weight')
            ->withTimestamps();
    }

    /**
     * Get the comments for this item.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

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
}
