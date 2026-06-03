<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Writes `blizzard_icons` media onto System A's shared disk path so that a single
 * physical file backs both Media Library and ServeIconController:
 *
 *   blizzard-cdn/icons/{size}/{name}
 *
 * The size comes from the media's `size` custom property (default 56); the name is
 * the media's file_name. Mirrors the `blizzard-cdn` prefix used by MirrorPaths so
 * the two systems cannot drift.
 */
class BlizzardIconPathGenerator implements HasBlizzardIcons, PathGenerator
{
    private const PREFIX = MirrorPaths::PREFIX;

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

        return $this->directory($media).$media->file_name;
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
        $size = (int) ($media->getCustomProperty('size') ?? self::BLIZZARD_ICON_SIZE);

        return self::PREFIX."/icons/{$size}/";
    }
}
