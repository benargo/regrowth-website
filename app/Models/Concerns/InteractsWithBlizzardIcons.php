<?php

namespace App\Models\Concerns;

use App\Contracts\HasBlizzardIcons;
use App\Support\MediaLibrary\UnknownBlizzardIcon;

/**
 * Registers the single-file `blizzard_icons` Media Library collection on a HasBlizzardIcons model.
 */
trait InteractsWithBlizzardIcons
{
    /**
     * Add the `blizzard_icons` collection, falling back to the questionmark icon.
     */
    protected function addBlizzardIconCollection(): void
    {
        $this->addMediaCollection(HasBlizzardIcons::MEDIA_COLLECTION)
            ->singleFile()
            ->useFallbackUrl(app(UnknownBlizzardIcon::class)->url());
    }
}
