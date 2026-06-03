<?php

namespace App\Models\LootCouncil;

use App\Contracts\Models\HasBlizzardIcons;
use App\Models\Item;
use Database\Factories\LootCouncil\PriorityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Priority extends Model implements HasBlizzardIcons, HasMedia
{
    /** @use HasFactory<PriorityFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lootcouncil_priorities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'type',
    ];

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
     * Get the items that have this priority.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'lootcouncil_item_priorities', 'priority_id', 'item_id')
            ->withPivot('weight')
            ->withTimestamps();
    }
}
