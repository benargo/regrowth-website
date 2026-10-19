<?php

namespace App\Data\Navigation;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class NextRaidData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly CarbonImmutable $startTime,
        public readonly string $planUrl,
        public readonly ?SignupLineData $signup,
    ) {}

    /**
     * Describes an event's title, start time and plan link, with the viewer's signup line.
     */
    public static function fromEvent(Event $event, ?SignupLineData $signup): self
    {
        return new self(
            id: $event->id,
            title: $event->title,
            startTime: $event->start_time->toImmutable(),
            planUrl: route('raiding.plans.show', $event),
            signup: $signup,
        );
    }
}
