<?php

namespace App\Models;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use Database\Factories\RaidFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

#[Appends(['slug'])]
class Raid extends Model
{
    /** @use HasFactory<RaidFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'raids';

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
        'max_loot_councillors',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_players' => 'integer',
        'max_loot_councillors' => 'integer',
    ];

    // ============ Custom attributes ============

    /**
     * Get the slug attribute for the raid, which is a URL-friendly version of the raid name.
     *
     * This is not stored in the database, but is generated on the fly when accessed. It is used for creating SEO-friendly URLs for raids.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        );
    }

    /**
     * Get the max_groups attribute for the raid, which is the maximum number of groups that can be formed based on the max_players attribute.
     */
    protected function maxGroups(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ceil($this->max_players / 5),
        );
    }

    // ============ Dataset relationships ============

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
     * Get the phase that this raid belongs to.
     *
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    // ============ Event relationships ============

    /**
     * Get the events that are associated with this raid.
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'pivot_events_raids', 'raid_id', 'event_id')
            ->withTimestamps();
    }

    // ========== Loot bias relationships ==========

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
