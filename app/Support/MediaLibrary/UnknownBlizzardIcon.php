<?php

namespace App\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use Illuminate\Container\Attributes\Config;
use Illuminate\Filesystem\FilesystemManager;

final class UnknownBlizzardIcon implements HasBlizzardIcons
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        #[Config('media-library.disk_name')]
        private readonly string $diskName,
    ) {}

    /**
     * The object path of the questionmark icon on the media disk.
     */
    public function path(): string
    {
        return self::STORAGE_PATH_PREFIX.'/icons/'.self::DEFAULT_MEDIA_SIZE.'/'.self::BLIZZARD_UNKNOWN_ICON.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION;
    }

    /**
     * The public URL of the questionmark icon on the current media disk.
     */
    public function url(): string
    {
        return $this->filesystem->disk($this->diskName)->url($this->path());
    }
}
