<?php

namespace App\Models;

use App\Events\ItemPriorityDeleted;
use App\Events\ItemPrioritySaved;
use Database\Factories\ItemPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(
    name: 'pivot_items_priorities',
    key: 'id',
    incrementing: true,
)]
#[Fillable(['item_id', 'priority_id', 'weight'])]
#[Hidden(['created_at', 'updated_at'])]
class ItemPriority extends Pivot
{
    /** @use HasFactory<ItemPriorityFactory> */
    use HasFactory;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'saved' => ItemPrioritySaved::class,
        'deleted' => ItemPriorityDeleted::class,
    ];

    /**
     * Get the item that this priority belongs to.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the priority.
     *
     * @return BelongsTo<LootPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(LootPriority::class);
    }
}
