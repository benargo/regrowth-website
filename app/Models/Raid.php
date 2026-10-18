<?php

namespace App\Models;

use App\Casts\AsBinaryColor;
use App\Models\Concerns\FlushesRaidingCacheOnSave;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Appends(['slug'])]
#[Fillable(['name', 'difficulty', 'color', 'phase_id', 'max_players', 'max_loot_councillors'])]
#[Hidden(['created_at', 'updated_at'])]
class Raid extends Model implements HasMedia
{
    use FlushesRaidingCacheOnSave;
    use HasFactory;
    use InteractsWithMedia;

    /**
     * The wide header image shown on the raid's pages.
     */
    public const string MEDIA_BANNER = 'banner';

    /**
     * The resized webp version of the banner shown in page headers.
     */
    public const string MEDIA_CONVERSION_HEADER = 'header';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => AsBinaryColor::class,
            'max_players' => 'integer',
            'max_loot_councillors' => 'integer',
        ];
    }

    // ============ Custom attributes ============

    /**
     * Get the URL of the banner's header conversion, or of the original until the conversion exists.
     *
     * @return Attribute<string|null>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->getFirstMedia(self::MEDIA_BANNER)?->getAvailableUrl([self::MEDIA_CONVERSION_HEADER]),
        )->shouldCache();
    }

    /**
     * Get the CSS background position stored on the banner, if any.
     *
     * @return Attribute<string|null>
     */
    protected function bannerPosition(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->getFirstMedia(self::MEDIA_BANNER)?->getCustomProperty('position'),
        )->shouldCache();
    }

    /**
     * Get the max_groups attribute for the raid, which is the maximum number of groups that can be formed based on the max_players attribute.
     *
     * @return Attribute<int>
     */
    protected function maxGroups(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ceil($this->max_players / 5),
        );
    }

    /**
     * Get the slug attribute for the raid, which is a URL-friendly version of the raid name.
     *
     * @return Attribute<string>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        );
    }

    // ============ Media ============

    /**
     * Get the media attached to the raid, each with its `model` relation set to the raid.
     *
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany($this->getMediaModel(), 'model')->chaperone('model');
    }

    /**
     * Register the single-file banner collection.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_BANNER)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register the webp header conversion of the banner, at most 1920×1080.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_CONVERSION_HEADER)
            ->fit(Fit::Max, 1920, 1080)
            ->format('webp')
            ->performOnCollections(self::MEDIA_BANNER);
    }

    // ============ Dataset relationships ============

    /**
     * Get the bosses in this raid.
     *
     * @return HasMany<Boss, $this>
     */
    public function bosses(): HasMany
    {
        return $this->hasMany(Boss::class)->orderBy('sort_order');
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
     *
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'pivot_events_raids', 'raid_id', 'event_id')
            ->using(EventRaid::class)
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    // ========== Loot bias relationships ==========

    /**
     * Get the items that drop in this raid.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'pivot_items_raids', 'raid_id', 'item_id')
            ->withTimestamps();
    }

    /**
     * Get the trash items that drop in this raid (items without a boss).
     *
     * @return BelongsToMany<Item, $this>
     */
    public function trashItems(): BelongsToMany
    {
        return $this->items()->trash();
    }

    /**
     * Get the comments on the items that drop in this raid (including trash drops).
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return Relation::noConstraints(fn () => $this->hasMany(Comment::class, 'commentable_id')
            ->where('commentable_type', Item::class)
            ->whereIn('commentable_id', $this->items()->select('items.id')));
    }
}
