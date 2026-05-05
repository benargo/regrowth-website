<?php

namespace App\Http\Resources;

use App\Models\Character;
use App\Models\Raid;
use App\Services\RaidHelper\Resources\Comp;
use App\Services\RaidHelper\Resources\CompGroup;
use App\Services\RaidHelper\Resources\CompSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EventGroupsResource extends JsonResource
{
    /**
     * The slots associated with this event, mapped to their assigned characters.
     *
     * @var Collection<int, Character>
     */
    private ?Collection $characters = null;

    /**
     * The groups associated with this event.
     *
     * @var Collection<int, CompGroup>
     */
    private $groups;

    /**
     * The raid associated with this resource, if any.
     */
    private ?Raid $raid = null;

    /**
     * The slots associated with this event.
     *
     * @var Collection<int, CompSlot>
     */
    private $slots;

    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     *
     * @throws InvalidArgumentException if the resource is not a Comp data object.
     */
    public function __construct($resource)
    {
        if (! ($resource instanceof Comp)) {
            throw new InvalidArgumentException('EventGroupsResource can only be created from a Comp data object.');
        }

        parent::__construct($resource);

        $this->groups = collect($this->resource->groups);
        $this->slots = collect($this->resource->slots);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->groups->mapWithKeys(function (CompGroup $group) {
            $groupSlots = $this->slots->where('groupNumber', $group->position)->values();
            $characters = $this->getCharacters()->only($groupSlots->pluck('id'));

            $mappedSlots = $groupSlots->mapWithKeys(function (CompSlot $slot) use ($characters) {
                $character = $characters->get($slot->id);

                // Position is calculated as follows:
                // - Each group has 5 slots, so we multiply the group number (minus 1, since group numbers are 1-indexed) by 5 to get the starting position for the group.
                // - We then add the slot number (minus 1, since slot numbers are also 1-indexed) to get the final position for the slot.
                $slotPosition = ($slot->groupNumber - 1) * 5 + $slot->slotNumber - 1;

                if (! $character) {
                    return [$slotPosition => null];
                }

                return [$slotPosition => $character->toResource()];
            });

            return [($group->position - 1) => $mappedSlots->all()];
        })->all();
    }

    /**
     * Associate a raid with this resource.
     */
    public function forRaid(Raid $raid): self
    {
        $this->raid = $raid;

        // Take only the groups that can be filled with the maximum number of players in the raid.
        $this->groups = $this->groups->takeWhile(fn (CompGroup $group) => $group->position <= $raid->max_groups);

        // Take only the slots that belong to the groups that can be filled with the maximum number of players in the raid.
        $this->slots = $this->slots->filter(function (CompSlot $slot) use ($raid) {
            return $slot->groupNumber <= $raid->max_groups;
        });

        if ($this->characters) {
            // If the characters have already been loaded, take only the characters that are assigned to the remaining slots.
            $this->characters = $this->characters->only($this->slots->pluck('id'));
        }

        return $this;
    }

    // ============ Helper methods ============

    /**
     * Load the characters for the slots in this comp.
     *
     * This should be called after the resource is created, but before it is returned from the controller.
     *
     * @return Collection<int, Character>
     */
    private function getCharacters(): Collection
    {
        if ($this->characters) {
            // If the characters have already been loaded, return them.
            return $this->characters;
        }

        $characters = [];

        $this->slots->each(function (CompSlot $slot) use (&$characters) {
            $character = Character::where('name', $slot->name)->first();

            if (! $character) {
                return;
            }

            $characters[$slot->id] = $character;
        });

        $this->characters = collect($characters);

        return $this->characters;
    }
}
