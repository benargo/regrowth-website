<?php

namespace App\Models;

use App\Casts\AsBinaryColor;
use App\Contracts\Models\BelongsToGameVersion;
use App\Contracts\Models\DatasetModel;
use App\Contracts\Models\DispatchesChangesForMedia;
use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use App\Contracts\Models\SharesRaidBannerCollection;
use App\Events\RaidingDataChanged;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\InteractsWithChaperonedMedia;
use App\Models\Concerns\TracksUsage;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Appends(['slug'])]
#[Fillable(['name', 'difficulty', 'color', 'phase_id', 'max_players', 'max_loot_councillors'])]
#[Hidden(['created_at', 'updated_at'])]
#[UsePolicy(DatasetPolicy::class)]
class Raid extends Model implements BelongsToGameVersion, DatasetModel, DispatchesChangesForMedia, EditLockable, HasMedia, HasUsage, SharesRaidBannerCollection
{
    use HasEditLock;
    use HasFactory;
    use InteractsWithChaperonedMedia;
    use TracksUsage;

    /**
     * The resized webp version of the banner shown in page headers.
     */
    public const string MEDIA_CONVERSION_HEADER = 'header';

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => RaidingDataChanged::class,
        'updated' => RaidingDataChanged::class,
        'deleted' => RaidingDataChanged::class,
    ];

    /**
     * Relationships whose existing rows mark this raid as in use.
     *
     * @return list<string>
     */
    public function usageRelations(): array
    {
        return ['bosses', 'upcomingEvents'];
    }

    /**
     * The event dispatched with this model when its media changes.
     *
     * @return class-string<RaidingDataChanged>
     */
    public function mediaChangedEvent(): string
    {
        return RaidingDataChanged::class;
    }

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
     * Store the difficulty in title case, e.g. "heroic" as "Heroic".
     *
     * @return Attribute<string, string>
     */
    protected function difficulty(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::title($value),
        );
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

    // ============ Game version ============

    /**
     * Get the game version key of the raid's phase, or null when the phase has none.
     *
     * @return Attribute<int|null>
     */
    protected function gameVersionId(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->phase?->game_version_id,
        );
    }

    /**
     * Scope a query to raids whose phase belongs to the given game version, or to none when null.
     */
    #[Scope]
    protected function inGameVersion(Builder $query, ?GameVersion $gameVersion): void
    {
        $query->whereHas('phase', fn (Builder $phases) => $phases->where('game_version_id', $gameVersion?->getKey()));
    }

    // ============ Media ============

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

    /**
     * Get the events with this raid that have ended.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function pastEvents(): BelongsToMany
    {
        return $this->events()->where('events.end_time', '<', now());
    }

    /**
     * Get the event templates with this raid.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function templateEvents(): BelongsToMany
    {
        return $this->events()->templates();
    }

    /**
     * Get the events with this raid that are upcoming or still in progress.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function upcomingEvents(): BelongsToMany
    {
        return $this->events()->where('events.end_time', '>=', now());
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
