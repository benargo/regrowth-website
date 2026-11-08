<?php

namespace App\Models;

use App\Contracts\HasShadowAvatars;
use App\Enums\Faction;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[WithoutIncrementing]
#[WithoutTimestamps]
#[Fillable(['id', 'name', 'faction'])]
class PlayableRace extends Model implements HasMedia, HasShadowAvatars
{
    use HasFactory, InteractsWithMedia;

    /**
     * Register the `shadow_avatars` collection: one Blizzard silhouette per gender,
     * keyed by the `gender` custom property.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'faction' => Faction::class,
        ];
    }

    /**
     * Get the characters of this playable race.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return BelongsToMany<GameVersion, $this>
     */
    public function gameVersions(): BelongsToMany
    {
        return $this->belongsToMany(GameVersion::class, 'pivot_game_versions_playable_races')
            ->withTimestamps();
    }

    // ============ Shadow avatars ============

    /**
     * Whether the shadow avatar for the given gender is stored.
     */
    public function hasShadowAvatar(Gender $gender): bool
    {
        return $this->shadowAvatar($gender) !== null;
    }

    /**
     * Whether a shadow avatar is stored for every gender. Counts in memory when
     * the media relation is loaded, otherwise runs a single count query.
     */
    public function hasAllShadowAvatars(): bool
    {
        $storedCount = $this->relationLoaded('media')
            ? $this->media->where('collection_name', self::MEDIA_COLLECTION)->count()
            : $this->media()->where('collection_name', self::MEDIA_COLLECTION)->count();

        return $storedCount >= count(Gender::cases());
    }

    /**
     * The URL of the stored shadow avatar for the given gender, or null when none is stored.
     */
    public function shadowAvatarUrl(Gender $gender): ?string
    {
        return $this->shadowAvatar($gender)?->getUrl();
    }

    /**
     * Remember for seven days that the render CDN has no shadow avatar for the given gender.
     */
    public function rememberShadowAvatarMissing(Gender $gender): void
    {
        Cache::put($this->shadowAvatarMissingCacheKey($gender), true, self::SHADOW_AVATAR_MISSING_FOR_SECONDS);
    }

    /**
     * Whether the render CDN was recently found to have no shadow avatar for the given gender.
     */
    public function isShadowAvatarKnownMissing(Gender $gender): bool
    {
        return Cache::has($this->shadowAvatarMissingCacheKey($gender));
    }

    private function shadowAvatarMissingCacheKey(Gender $gender): string
    {
        return "shadow-avatar:missing:{$this->id}-{$gender->id()}";
    }

    private function shadowAvatar(Gender $gender): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_COLLECTION, ['gender' => $gender->id()]);
    }
}
