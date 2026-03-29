<?php

namespace App\Models\TBC;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use Database\Factories\TBC\RaidFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Raid extends Model
{
    /** @use HasFactory<RaidFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbc_raids';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'difficulty',
        'phase_id',
        'max_players',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Get the phase that this raid belongs to.
     *
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * Get the bosses in this raid.
     *
     * @return HasMany<Boss, $this>
     */
    public function bosses(): HasMany
    {
        return $this->hasMany(Boss::class);
    }

    /**
     * Get the items that drop from this raid.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'raid_id');
    }

    /**
     * Get the trash items that drop from this raid (items without a boss).
     *
     * @return HasMany<Item, $this>
     */
    public function trashItems(): HasMany
    {
        return $this->items()->whereNull('boss_id');
    }

    /**
     * Get the comments for the items that drop from this raid (including trash drops).
     *
     * @return HasManyThrough<Comment, Item>
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Item::class, 'raid_id', 'item_id');
    }
}
