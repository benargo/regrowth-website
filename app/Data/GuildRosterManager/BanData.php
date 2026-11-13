<?php

namespace App\Data\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * A banned character.
 *
 * Mapped from GRM's `bannedInfo` = `{banned, banEpoch, unbanned, bannedBy}` and the record's `reasonBanned`;
 * for an unbanned row, `banned_at` and `banned_by` are the unban time and who unbanned.
 */
final class BanData extends Data implements RosterEntryData
{
    /**
     * @param  string|null  $banned_at  A UTC `Y-m-d\TH:i:s\Z` timestamp, kept as a string.
     * @param  int  $epoch  GRM's `bannedInfo[2]`.
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $name,
        public readonly bool $unbanned,
        #[MapName(CamelCaseMapper::class)]
        public readonly ?string $banned_at,
        #[MapName(CamelCaseMapper::class)]
        public readonly ?string $banned_by,
        public readonly ?string $reason,
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
     * Get the fields the ban's fingerprint is computed from.
     *
     * @return array<string, mixed>
     */
    public function fingerprintPayload(): array
    {
        return [
            'bannedAt' => $this->banned_at,
            'bannedBy' => $this->banned_by,
            'guid' => $this->guid,
            'name' => $this->name,
            'reason' => $this->reason,
            'unbanned' => $this->unbanned,
        ];
    }
}
