<?php

namespace App\Services\Attendance;

use App\Models\Character;
use Spatie\LaravelData\Data;

final class PlayerPresenceData extends Data
{
    public function __construct(
        public readonly Character $character,
        public readonly int $presence,
    ) {}

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: mixed, presence: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->character->id,
            'name' => $this->character->name,
            'rank_id' => $this->character->rank_id,
            'playable_class' => $this->character->playable_class,
            'presence' => $this->presence,
        ];
    }

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: mixed, presence: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
