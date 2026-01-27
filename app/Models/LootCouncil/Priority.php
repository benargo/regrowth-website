<?php

namespace App\Models\LootCouncil;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Priority extends Model
{
    /** @use HasFactory<\Database\Factories\LootCouncil\PriorityFactory> */
    use HasFactory;

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
        'media',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var list<string, string>
     */
    protected $casts = [
        'media' => 'json',
    ];

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
