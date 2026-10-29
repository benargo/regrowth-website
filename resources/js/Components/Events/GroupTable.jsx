import cn from "@/Helpers/cn";
import RankLabel from "@/Components/GuildRanks/RankLabel";
import LootCouncillorBadge from "@/Components/Events/LootCouncillorBadge";
import RoleBadge from "@/Components/Events/RoleBadge";
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from "@/Components/Table";

function BenchedPill({ character }) {
    const classSlug = character.playable_class?.slug;
    const colorClass = classSlug ? `playable-class-${classSlug}` : "line";

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
                <Table>
                    <TableHead>
                        <tr>
                            <TableHeader className="text-left">Character</TableHeader>
                            <TableHeader className="text-left">Rank</TableHeader>
                            <TableHeader className="text-left">Class</TableHeader>
                        </tr>
                    </TableHead>
                    <TableBody>
                        {group.characters.map((character) => (
                            <TableRow
                                key={character.id}
                                className={cn(
                                    character.signup_status === "unconfirmed" && "opacity-40",
                                    character.signup_status === "cancelled" &&
                                        "text-red-400 line-through decoration-red-400 opacity-40",
                                )}
                            >
                                <TableCell>
                                    <span className="inline-flex items-center gap-1">
                                        <span className="text-sm font-medium text-white">{character.name}</span>
                                        {character.is_loot_councillor && <LootCouncillorBadge />}
                                        {character.is_loot_master && <RoleBadge role="loot_master" />}
                                        {character.is_leader && <RoleBadge role="leader" />}
                                    </span>
                                </TableCell>
                                <TableCell className="text-sm text-gray-300">
                                    {character.rank ? <RankLabel rank={character.rank} /> : "—"}
                                </TableCell>
                                <TableCell>
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
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
