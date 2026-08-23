<?php

namespace App\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\LootPriorityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['title', 'type', 'playable_class_id'])]
#[Hidden(['created_at', 'updated_at'])]
class LootPriority extends Model implements HasBlizzardIcons, HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LootPriorityType::class,
        ];
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
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'pivot_items_priorities', 'priority_id', 'item_id')
            ->withPivot('weight')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<PlayableClass, $this>
     */
    public function playableClass(): BelongsTo
    {
        return $this->belongsTo(PlayableClass::class);
    }
}
