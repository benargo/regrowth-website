<?php

namespace App\Data\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * A character that has left the guild.
 */
final class LeaverData extends Data implements RosterEntryData
{
    /**
     * @param  array<int, MembershipEventData>  $history
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $name,
        #[MapName(CamelCaseMapper::class)]
        public readonly int $rank_index,
        public readonly int $level,
        public readonly string $class,
        public readonly string $race,
        public readonly array $history,
        public readonly int $epoch = 0,
    ) {}

    /**
     * Get the character's GUID.
     */
    public function key(): string
    {
        return $this->guid;
    }

    /**
     * Get the fields the leaver's fingerprint is computed from.
     *
     * @return array<string, mixed>
     */
    public function fingerprintPayload(): array
    {
        return [
            'class' => $this->class,
            'guid' => $this->guid,
            'history' => collect($this->history)
                ->map(fn (MembershipEventData $event): array => ['date' => $event->date, 'type' => $event->type])
                ->all(),
            'level' => $this->level,
            'name' => $this->name,
            'race' => $this->race,
            'rankIndex' => $this->rank_index,
        ];
    }
}
