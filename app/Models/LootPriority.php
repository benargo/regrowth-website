<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['title', 'type'])]
#[Hidden(['created_at', 'updated_at'])]
class LootPriority extends Model implements HasBlizzardIcons, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

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
        return $this->belongsToMany(Item::class, 'pivot_items_priorities', 'priority_id', 'item_id')
            ->withPivot('weight')
            ->withTimestamps();
    }
}
