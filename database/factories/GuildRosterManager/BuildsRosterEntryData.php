<?php

namespace Database\Factories\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\MembershipEventData;
use App\Enums\GuildRosterManager\RosterEntryKind;

/**
 * Builds a faked roster row Data object of a given kind for a GRM roster factory.
 */
trait BuildsRosterEntryData
{
    /**
     * Build a faked Data object of the kind, keyed by the given GUID or alt group ID.
     */
    protected function rosterEntryData(RosterEntryKind|string $kind, string $key, int $epoch): RosterEntryData
    {
        $kind = $kind instanceof RosterEntryKind ? $kind : RosterEntryKind::from($kind);
        $history = [new MembershipEventData(type: 'joined', date: fake()->date())];

        return match ($kind) {
            RosterEntryKind::Member => new MemberData(
                guid: $key,
                name: fake()->firstName(),
                rank_index: fake()->numberBetween(0, 9),
                level: fake()->numberBetween(1, 60),
                class: 'WARRIOR',
                race: 'Human',
                alt_group: null,
                history: $history,
                epoch: $epoch,
            ),
            RosterEntryKind::Leaver => new LeaverData(
                guid: $key,
                name: fake()->firstName(),
                rank_index: fake()->numberBetween(0, 9),
                level: fake()->numberBetween(1, 60),
                class: 'WARRIOR',
                race: 'Human',
                history: [...$history, new MembershipEventData(type: 'left', date: fake()->date())],
                epoch: $epoch,
            ),
            RosterEntryKind::AltGroup => new AltGroupData(
                id: $key,
                main: fake()->firstName(),
                members: ['Player-'.fake()->numberBetween(1, 9999).'-'.strtoupper(fake()->bothify('????????'))],
                epoch: $epoch,
            ),
            RosterEntryKind::Ban => new BanData(
                guid: $key,
                name: fake()->firstName(),
                unbanned: false,
                banned_at: null,
                banned_by: null,
                reason: null,
                epoch: $epoch,
            ),
        };
    }
}
