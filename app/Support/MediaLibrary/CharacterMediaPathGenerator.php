<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasCharacterMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores `character_portraits` media under a realm-namespaced path:
 *
 *   blizzard-cdn/characters/{realmSlug}/{size}/{filename}
 *
 * The realm slug is injected via the constructor so it can be resolved from config
 * by the service container, keeping the generator testable with any slug.
 */
class CharacterMediaPathGenerator implements HasCharacterMedia, PathGenerator
{
    private DefaultPathGenerator $default;

    public function __construct(private readonly string $realmSlug)
    {
        $this->default = new DefaultPathGenerator;
    }

    public function getPath(Media $media): string
    {
        if (! $this->isCharacterPortrait($media)) {
            return $this->default->getPath($media);
        }

        return $this->directory($media).$media->file_name;
    }

    public function getPathForConversions(Media $media): string
    {
        if (! $this->isCharacterPortrait($media)) {
            return $this->default->getPathForConversions($media);
        }

        return $this->directory($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if (! $this->isCharacterPortrait($media)) {
            return $this->default->getPathForResponsiveImages($media);
        }

        return $this->directory($media).'responsive-images/';
    }

    private function isCharacterPortrait(Media $media): bool
    {
        return $media->collection_name === self::MEDIA_COLLECTION;
    }

    /**
     * The directory path (with trailing slash) for this portrait.
     */
    private function directory(Media $media): string
    {
        $size = (int) ($media->getCustomProperty('size') ?? self::DEFAULT_MEDIA_SIZE);

        return self::STORAGE_PATH_PREFIX."/{$this->realmSlug}/{$size}/";
    }
}
