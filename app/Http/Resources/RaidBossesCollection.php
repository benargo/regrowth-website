<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

#[Collects(BossResource::class)]
class RaidBossesCollection extends ResourceCollection
{
    /**
     * Disable the data wrapper since we are performing this manually.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * The collection of resources.
     *
     * @var Collection<int, BossResource>
     */
    private $assignments = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection
                ->groupBy(fn (BossResource $boss) => (string) $boss->raid_id)
                ->map(fn ($group) => $group->map(function (BossResource $boss) use ($request) {
                    $boss->mergeWhen(
                        $this->assignments?->has($boss->id),
                        fn () => [
                            'assignments' => $this->assignments->where('boss_id', $boss->id)->toResourceCollection(EventAssignmentResource::class),
                        ]
                    );

                    return $boss->resolve($request);
                })->values()),
            'raid_ids' => $this->collection->pluck('raid_id')->unique()->values()->toArray(),
        ];
    }

    /**
     * Load the assignments for the event and group them by boss ID for easy access when resolving the boss resources.
     *
     * @return $this
     */
    public function withAssignments(Event $event): self
    {
        $this->assignments = $event->assignments()->get();

        return $this;
    }
}
