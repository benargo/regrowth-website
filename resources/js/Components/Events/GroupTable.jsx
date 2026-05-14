import GuildRankLabel from "@/Components/GuildRankLabel";
import LootCouncillorBadge from "@/Components/Events/LootCouncillorBadge";
import RoleBadge from "@/Components/Events/RoleBadge";

function BenchedPill({ character }) {
    const classSlug = character.playable_class?.slug;
    const colorClass = classSlug ? `playable-class-${classSlug}` : "brown-600";

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border border-solid border-${colorClass} bg-${colorClass}/10 px-3 py-1 text-sm font-medium text-white`}
        >
            {character.playable_class?.icon_url && (
                <img
                    src={character.playable_class.icon_url}
                    alt={character.playable_class.name}
                    className="h-4 w-4 shrink-0"
                />
            )}
            {character.name}
        </span>
    );
}

export function BenchedTable({ characters }) {
    return (
        <div className="flex flex-col">
            <h2 className="mb-4 text-xl font-semibold text-white">
                Benched
                <span className="ml-2 text-base font-normal text-gray-400">({characters.length})</span>
            </h2>

            <div className="flex flex-wrap gap-2 rounded border border-amber-600/30 p-4">
                {characters.map((character) => (
                    <BenchedPill key={character.id} character={character} />
                ))}
            </div>
        </div>
    );
}

export function GroupTable({ group }) {
    return (
        <div className="flex flex-col">
            <h2 className="mb-4 text-xl font-semibold text-white">
                {group.is_team ? "Team" : "Group"} {group.group_number}
                <span className="ml-2 text-base font-normal text-gray-400">({group.characters.length})</span>
            </h2>
            <div className="flex flex-1 flex-col rounded border border-amber-600/30">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse">
                        <thead className="border-b border-amber-600">
                            <tr>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Character</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Rank</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Class</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-brown-700">
                            {group.characters.map((character) => (
                                <tr
                                    key={character.id}
                                    className={`transition-colors hover:bg-brown-800/50${!character.is_confirmed ? " opacity-40" : ""}`}
                                >
                                    <td className="px-4 py-3">
                                        <span className="inline-flex items-center gap-1">
                                            <span className="text-sm font-medium text-white">{character.name}</span>
                                            {character.is_loot_councillor && <LootCouncillorBadge />}
                                            {character.is_loot_master && <RoleBadge role="loot_master" />}
                                            {character.is_leader && <RoleBadge role="leader" />}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-300">
                                        {character.rank ? <GuildRankLabel rank={character.rank} /> : "—"}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2 text-sm text-gray-300">
                                            {character.playable_class?.icon_url && (
                                                <img
                                                    src={character.playable_class.icon_url}
                                                    alt={character.playable_class.name}
                                                    className="h-4 w-4"
                                                />
                                            )}
                                            {character.playable_class?.name ?? "—"}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
