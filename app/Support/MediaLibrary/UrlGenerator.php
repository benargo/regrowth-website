<?php

namespace App\Support\MediaLibrary;

use App\Contracts\Models\HasBlizzardIcons;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * For media owned by a HasBlizzardIcons model, returns a signed icons.show URL
 * (the resilient System A route). For all other media, defers to the default
 * /storage/... URL behaviour, keeping Boss and any other default-collection media
 * unaffected.
 *
 * Only getUrl() is overridden intentionally. Blizzard icons are single-file with no
 * conversions and are served exclusively via the signed icons.show route, so
 * getTemporaryUrl(), conversion URL, and responsive image URL methods deliberately
 * keep their default behaviour.
 */
class UrlGenerator extends DefaultUrlGenerator
{
    /**
     * Return a signed icons.show URL for HasBlizzardIcons media; delegate to the
     * default /storage URL for all other media.
     */
    public function getUrl(): string
    {
        if (is_a($this->media->model, HasBlizzardIcons::class) && $this->media->collection_name === 'blizzard_icons') {
            $size = (int) ($this->media->getCustomProperty('size') ?? 56);

            return URL::signedRoute('icons.show', [
                'size' => $size,
                'name' => $this->media->file_name,
            ]);
        }

        return parent::getUrl();
    }
}
