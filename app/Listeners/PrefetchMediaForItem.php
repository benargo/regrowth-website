<?php

namespace App\Listeners;

use App\Events\ItemSaved;
use App\Services\Blizzard\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PrefetchMediaForItem implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected MediaService $media,
    ) {}

    /**
     * Get the middleware the listener should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(ItemSaved $event): array
    {
        return [
            new WithoutOverlapping($event->item->id),
            Skip::when(function () use ($event): bool {
                return $event->item->icon === null;
            }),
        ];
    }

    /**
     * Handle the event.
     */
    public function handle(ItemSaved $event): void
    {
        $this->media->download($event->item->icon->assets);
    }
}
