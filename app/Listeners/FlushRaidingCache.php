<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\Raid;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class FlushRaidingCache
{
    /**
     * Handle the event.
     */
    public function handle(FlushesRaidingCache $event): void
    {
        $this->flush();
    }

    /**
     * Flush the cache when a conversion of a raid's media has been generated.
     */
    public function handleConversionCompleted(ConversionHasBeenCompletedEvent $event): void
    {
        if ($event->media->model_type === (new Raid)->getMorphClass()) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
