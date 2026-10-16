<?php

namespace App\Data\Attendance;

use App\Enums\Presence;
use App\Models\Character;
use App\Models\PlayableClass;
use Spatie\LaravelData\Data;

/**
 * A character paired with its presence in a raid.
 */
final class PlayerPresenceData extends Data
{
    public function __construct(
        public readonly Character $character,
        public readonly Presence $presence,
    ) {}

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: PlayableClass|null, presence: Presence}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->character->id,
            'name' => $this->character->name,
            'rank_id' => $this->character->rank_id,
            'playable_class' => $this->character->playableClass,
            'presence' => $this->presence,
        ];
    }

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: PlayableClass|null, presence: Presence}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
