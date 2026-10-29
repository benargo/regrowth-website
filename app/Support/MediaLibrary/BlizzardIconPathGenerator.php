<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores `blizzard_icons` media under a size-namespaced directory:
 *
 *   blizzard-cdn/icons/{size}/{file_name}
 *
 * getPath() returns the directory only; Media Library appends the file name. The size
 * comes from the media's `size` custom property (default 56). Conversions and
 * responsive images nest under the same directory.
 */
class BlizzardIconPathGenerator implements HasBlizzardIcons, PathGenerator
{
    private DefaultPathGenerator $default;

    public function __construct()
    {
        $this->default = new DefaultPathGenerator;
    }

    public function getPath(Media $media): string
    {
        if ($media->collection_name !== 'blizzard_icons') {
            return $this->default->getPath($media);
        }

        return $this->directory($media);
    }

    public function getPathForConversions(Media $media): string
    {
        if ($media->collection_name !== 'blizzard_icons') {
            return $this->default->getPathForConversions($media);
        }

        return $this->directory($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if ($media->collection_name !== 'blizzard_icons') {
            return $this->default->getPathForResponsiveImages($media);
        }

        return $this->directory($media).'responsive-images/';
    }

    /**
     * The size directory (with trailing slash) that holds this icon and its derivatives.
     */
    private function directory(Media $media): string
    {
        $size = (int) ($media->getCustomProperty('size') ?? self::DEFAULT_MEDIA_SIZE);

        return self::STORAGE_PATH_PREFIX."/icons/{$size}/";
    }
}
