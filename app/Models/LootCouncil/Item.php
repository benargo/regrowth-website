<?php

namespace App\Models\LootCouncil;

use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\LootCouncil\ItemFactory> */
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
        'group',
    ];

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
            ->withPivot('weight')
            ->withTimestamps();
    }
}
