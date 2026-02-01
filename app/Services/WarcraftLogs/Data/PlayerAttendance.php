<?php

namespace App\Services\WarcraftLogs\Data;

readonly class PlayerAttendance
{
    public function __construct(
        /**
         * The name of the player.
         *
         * @param  string  $name
         */
        public string $name,

        /**
         * Presence info for the player. A value of 1 means the player was present. A value
         * of 2 indicates present but on the bench.
         *
         * @param  int  $presence
         */
        public int $presence,
    ) {}

    /**
     * @param  array{name: string, type: string, presence: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            presence: $data['presence'],
        );
    }

    /**
     * @return array{name: string, type: string, presence: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'presence' => $this->presence,
        ];
    }
}
