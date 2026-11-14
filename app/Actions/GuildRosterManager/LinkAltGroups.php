<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\RowResultData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Applies GRM alt groups to the game version's characters. Members of a group are linked to each
 * other in both directions, the group's main is flagged as the main and the group's other members
 * are flagged as not main. A character the group listed previously and no longer lists loses its
 * links to the group's previous members only; its other links are kept. Unknown GUIDs are ignored.
 */
class LinkAltGroups
{
    use AsAction;

    /**
     * @param  Collection<int, array{id: string, payload: AltGroupData, previous: ?AltGroupData}>  $groups  The changed groups, each with its current payload and previous payload.
     * @return list<RowResultData>
     */
    public function handle(GameVersion $gameVersion, Collection $groups): array
    {
        $results = [];

        $charactersByGuid = $this->charactersByGuid($gameVersion, $groups);

        foreach ($groups as $group) {
            $results = [...$results, ...$this->detachFormerMembers($group, $charactersByGuid)];
        }

        foreach ($groups as $group) {
            $result = $this->attachMembers($gameVersion, $group, $charactersByGuid);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Load the characters of every GUID the groups mention, keyed by GUID.
     *
     * @param  Collection<int, array{id: string, payload: AltGroupData, previous: ?AltGroupData}>  $groups
     * @return Collection<string, Character>
     */
    private function charactersByGuid(GameVersion $gameVersion, Collection $groups): Collection
    {
        $guids = $groups
            ->flatMap(fn (array $group): array => [...$group['payload']->members, ...($group['previous']?->members ?? [])])
            ->unique()
            ->values();

        return Character::where('game_version_id', $gameVersion->id)
            ->whereIn('grm_guid', $guids)
            ->get()
            ->keyBy('grm_guid')
            ->toBase();
    }

    /**
     * Remove the links between each character the group dropped and the group's previous members.
     *
     * @param  array{id: string, payload: AltGroupData, previous: ?AltGroupData}  $group
     * @param  Collection<string, Character>  $charactersByGuid
     * @return list<RowResultData>
     */
    private function detachFormerMembers(array $group, Collection $charactersByGuid): array
    {
        if ($group['previous'] === null) {
            return [];
        }

        $dropped = array_diff($group['previous']->members, $group['payload']->members);
        $previousIds = $charactersByGuid->only($group['previous']->members)->pluck('id')->all();
        $results = [];

        foreach ($dropped as $guid) {
            $character = $charactersByGuid->get($guid);

            if ($character === null) {
                continue;
            }

            DB::table('character_links')
                ->where(fn ($query) => $query->where('character_id', $character->id)->whereIn('linked_character_id', $previousIds))
                ->orWhere(fn ($query) => $query->where('linked_character_id', $character->id)->whereIn('character_id', $previousIds))
                ->delete();

            $results[] = new RowResultData(
                characterId: $character->id,
                guid: $guid,
                name: $character->full_name,
                status: RowStatus::Processed,
                reason: RowReason::Detached,
                message: $group['id'],
            );
        }

        return $results;
    }

    /**
     * Link the group's members both ways and set the main flag. Returns a result when the main changed.
     *
     * @param  array{id: string, payload: AltGroupData, previous: ?AltGroupData}  $group
     * @param  Collection<string, Character>  $charactersByGuid
     */
    private function attachMembers(GameVersion $gameVersion, array $group, Collection $charactersByGuid): ?RowResultData
    {
        $members = $charactersByGuid->only($group['payload']->members)->values();

        foreach ($members as $member) {
            $member->linkedCharacters()->syncWithoutDetaching(
                $members->reject(fn (Character $other): bool => $other->is($member))->pluck('id')->all()
            );
        }

        if ($group['payload']->main === null) {
            return null;
        }

        $normalised = NormaliseName::run($gameVersion, $group['payload']->main);

        $main = $members->first(fn (Character $member): bool => $member->name === $normalised['name']
            && $member->surname === $normalised['surname']);

        if ($main === null) {
            return null;
        }

        $mainFlipped = ! $main->is_main;

        foreach ($members as $member) {
            $isMain = $member->is($main);

            if ($member->is_main !== $isMain) {
                $member->update(['is_main' => $isMain]);
            }
        }

        if (! $mainFlipped) {
            return null;
        }

        return new RowResultData(
            characterId: $main->id,
            guid: $main->grm_guid,
            name: $main->full_name,
            status: RowStatus::Processed,
            reason: RowReason::MainChanged,
            message: $group['id'],
        );
    }
}
