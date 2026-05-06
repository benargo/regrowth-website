import { Deferred } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import GuildRankLabel from "@/Components/GuildRankLabel";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";
import RoleBadge from "@/Helpers/RoleBadge";
import Tooltip from "@/Components/Tooltip";

function GroupsSkeleton() {
    return (
        <div className="animate-pulse space-y-0 overflow-hidden rounded border border-amber-600/30">
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    className="flex flex-col items-center gap-6 border-b border-brown-700 px-4 py-3 last:border-b-0 lg:flex-row"
                >
                    <div className="h-4 w-4 rounded bg-brown-700" />
                    <div className="h-4 w-4 rounded bg-brown-700" />
                    <div className="h-4 w-32 rounded bg-brown-700" />
                    <div className="h-4 w-16 rounded bg-brown-700" />
                </div>
            ))}
        </div>
    );
}

function BenchedSkeleton() {
    return (
        <div className="animate-pulse space-y-0 overflow-hidden rounded border border-amber-600/30">
            {Array.from({ length: 3 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3 border-b border-brown-700 px-4 py-3 last:border-b-0">
                    <div className="h-4 w-4 rounded bg-brown-700" />
                    <div className="h-4 w-4 rounded bg-brown-700" />
                    <div className="h-4 w-32 rounded bg-brown-700" />
                </div>
            ))}
        </div>
    );
}

function MetaItem({ icon, children }) {
    return (
        <div className="flex items-center gap-2 text-sm text-gray-300">
            <Icon icon={icon} style="solid" className="w-4 text-amber-500" />
            {children}
        </div>
    );
}

function LootCouncillorBadge() {
    return (
        <Tooltip text="Loot Councillor">
            <span className="rounded bg-discord-lootcouncillor px-1 py-0.5 text-xs text-white">LC</span>
        </Tooltip>
    );
}

function GroupTable({ group }) {
    return (
        <div className="mb-8 flex-initial">
            <h2 className="mb-4 text-xl font-semibold text-white">
                {group.is_team ? "Team" : "Group"} {group.group_number}
                <span className="ml-2 text-base font-normal text-gray-400">({group.characters.length})</span>
            </h2>
            <div className="rounded border border-amber-600/30">
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

function BenchedTable({ characters }) {
    return (
        <div className="rounded border border-amber-600/30">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead className="border-b border-amber-600">
                        <tr>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Character</th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Rank</th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Class</th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Race</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-brown-700">
                        {characters.map((character) => (
                            <tr key={character.id} className={!character.is_confirmed ? "opacity-40" : ""}>
                                <td className="px-4 py-3">
                                    <span className="text-sm font-medium text-white">{character.name}</span>
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
                                <td className="px-4 py-3 text-sm text-gray-300">
                                    <div className="flex items-center gap-2">
                                        {character.playable_race?.icon_url && (
                                            <img
                                                src={character.playable_race.icon_url}
                                                alt={character.playable_race.name}
                                                className="h-4 w-4"
                                            />
                                        )}
                                        {character.playable_race?.name ?? "—"}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Show({ event, raids, groups, benched }) {
    event = event.data ?? {}; // Handle Inertia resource wrapping
    const startDate = new Date(event.start_time);
    const endDate = new Date(event.end_time);
    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
    const formattedDate = formatDate(event.start_time);
    const duration = formatDuration({ seconds: event.duration });
    const formatTime = (date) => date.toLocaleString("en-GB", { hour: "2-digit", minute: "2-digit" });

    return (
        <Master title={event.title}>
            <SharedHeader title={event.title} backgroundClass={raids.background ?? "bg-illidan"} />

            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Event metadata card */}
                    <div className="mb-8 rounded border border-amber-600/30 bg-brown-800/50 p-4">
                        <div className="flex flex-wrap gap-x-8 gap-y-3">
                            <MetaItem icon="calendar">
                                <span>
                                    {dayOfWeek}, <span className="md:hidden">{formattedDate.short}</span>
                                    <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                    <span className="hidden lg:inline">{formattedDate.long}</span>
                                </span>
                            </MetaItem>
                            <MetaItem icon="clock">
                                {formatTime(startDate)}–{formatTime(endDate)} ({duration})
                            </MetaItem>
                            {(raids.data ?? []).length > 0 && (
                                <MetaItem icon="shield-alt">{raids.data.map((raid) => raid.name).join(", ")}</MetaItem>
                            )}
                        </div>
                    </div>

                    {/* Groups */}
                    <Deferred
                        data="groups"
                        fallback={
                            <>
                                <h2 className="mb-4 text-xl font-semibold text-white">Groups</h2>
                                <GroupsSkeleton />
                            </>
                        }
                    >
                        {groups?.data?.length > 0 ? (
                            <div className="flex flex-col gap-6 lg:flex-row">
                                {groups.data.map((group) => (
                                    <GroupTable key={group.group_number} group={group} />
                                ))}
                            </div>
                        ) : (
                            <p className="flex-1 text-center text-sm text-gray-400">
                                Groups for this raid have not been posted yet.
                            </p>
                        )}
                    </Deferred>

                    {/* Benched */}
                    <Deferred
                        data="benched"
                        fallback={
                            <>
                                <h2 className="mb-4 text-xl font-semibold text-white">Benched</h2>
                                <BenchedSkeleton />
                            </>
                        }
                    >
                        {(benched ?? []).length > 0 && (
                            <div>
                                <h2 className="mb-4 text-xl font-semibold text-white">
                                    Benched
                                    <span className="ml-2 text-base font-normal text-gray-400">({benched.length})</span>
                                </h2>
                                <BenchedTable characters={benched} />
                            </div>
                        )}
                    </Deferred>

                    {/** General assignments */}
                    {/**
                     * TODO:
                     */}

                    {/* Raids and bosses */}
                    {/**
                     * TODO: Loop over `raids.data` to display each raid and its bosses. If there is only one raid, then we don't need to show it's name.
                     * Once inside each raid, you can get the the raid's bosses by accessing the `bosses` prop, using the `id` of the raid as the key,
                     * e.g. `bosses.data.${raid_id}`. Loop through each of the bosses and create dropdowns, similar to the ones in
                     * @app/Pages/LootBiasTool/Raid.jsx. Each boss dropdown should show the boss name, but leave a visible 'TODO' inside the dropdown
                     * content for now.
                     * (This is because we are going to be adding in images and notes at a later date).
                     */}
                </div>
            </div>
        </Master>
    );
}
