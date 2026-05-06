<?php

namespace App\Models;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use Database\Factories\BossFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Boss extends Model implements HasMedia
{
    /** @use HasFactory<BossFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bosses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'raid_id',
        'encounter_order',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    // ============ Custom attributes ===========

    /**
     * Get the slug for the boss, generated from the name.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        )->shouldCache();
    }

    // ============ Relationships ===========

    /**
     * Get the raid that this boss belongs to.
     *
     * @return BelongsTo<Raid, $this>
     */
    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }

    /**
     * Get the items that drop from this boss.
     *
     * @return HasMany<Item>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'boss_id');
    }

    /**
     * Get the comments for the items that drop from this boss.
     *
     * @return HasManyThrough<Comment>
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Item::class, 'boss_id', 'item_id');
    }
}
