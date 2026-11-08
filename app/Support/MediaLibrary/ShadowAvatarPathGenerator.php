<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasShadowAvatars;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores `shadow_avatars` media in the shared mirror directory:
 *
 *   blizzard-cdn/shadow/avatar/{file_name}
 *
 * getPath() returns the directory only; Media Library appends the file name.
 * Conversions and responsive images nest under the same directory. Other
 * collections use Media Library's default paths.
 */
class ShadowAvatarPathGenerator implements HasShadowAvatars, PathGenerator
{
    private DefaultPathGenerator $default;

    public function __construct()
    {
        $this->default = new DefaultPathGenerator;
    }

    public function getPath(Media $media): string
    {
        if (! $this->isShadowAvatar($media)) {
            return $this->default->getPath($media);
        }

        return self::STORAGE_PATH_PREFIX.'/';
    }

    public function getPathForConversions(Media $media): string
    {
        if (! $this->isShadowAvatar($media)) {
            return $this->default->getPathForConversions($media);
        }

        return self::STORAGE_PATH_PREFIX.'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if (! $this->isShadowAvatar($media)) {
            return $this->default->getPathForResponsiveImages($media);
        }

        return self::STORAGE_PATH_PREFIX.'/responsive-images/';
    }

    private function isShadowAvatar(Media $media): bool
    {
        return $media->collection_name === self::MEDIA_COLLECTION;
    }
}
