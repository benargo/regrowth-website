import { Deferred } from "@inertiajs/react";
import { useState } from "react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Collapsible from "@/Components/Collapsible";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
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

function BossesSkeleton() {
    return (
        <div className="flex animate-pulse flex-col gap-2">
            {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="h-14 rounded-md border border-amber-600/30 bg-amber-600/10" />
            ))}
        </div>
    );
}

function BenchedSkeleton() {
    return (
        <div className="flex animate-pulse flex-wrap gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="h-7 w-24 rounded-full bg-brown-700" />
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

function BenchedPill({ character }) {
    const classSlug = character.playable_class?.name?.toLowerCase().replace(/\s+/g, "-");
    const borderClass = classSlug ? `border-playable-class-${classSlug}` : "border-brown-600";

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border bg-brown-800/80 px-3 py-1 text-sm font-medium text-white ${borderClass}`}
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

function BenchedTable({ characters }) {
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

export default function Show({ event, raids, groups, benched, bosses }) {
    event = event?.data ?? event ?? {}; // Handle Inertia resource wrapping
    benched = benched?.data ?? benched ?? {}; // Handle inertia resource wrapping
    groups = groups?.data ?? groups ?? {}; // Handle Inertia resource wrapping
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

                    {groups.length > 0 ? (
                        <div className="mb-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
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
                                {groups?.map((group) => (
                                    <GroupTable key={group.group_number} group={group} />
                                ))}
                            </Deferred>
                            {/* Benched */}
                            <Deferred
                                data="benched"
                                fallback={
                                    <>
                                        <h2 className="mb-2 text-xl font-semibold text-white">Benched</h2>
                                        <BenchedSkeleton />
                                    </>
                                }
                            >
                                {(benched ?? []).length > 0 && <BenchedTable characters={benched} />}
                            </Deferred>
                        </div>
                    ) : (
                        <p className="flex-1 text-center text-sm text-gray-400">
                            Groups for this raid have not been posted yet.
                        </p>
                    )}

                    {/** General assignments */}
                    {/**
                     * TODO: Instructions to follow for this section later.
                     */}

                    {/* Raids and bosses */}
                    {(raids.data ?? []).length > 0 && (
                        <div className="mt-8 space-y-8">
                            <Deferred data="bosses" fallback={<BossesSkeleton />}>
                                {(raids.data ?? []).map((raid) => {
                                    const raidBosses = bosses?.data?.[String(raid.id)] ?? [];
                                    return (
                                        <div key={raid.id}>
                                            {(raids.data ?? []).length > 1 && (
                                                <h2 className="mb-4 text-xl font-semibold text-white">{raid.name}</h2>
                                            )}
                                            <div className="flex flex-col gap-2">
                                                {raidBosses.map((boss) => (
                                                    <Collapsible
                                                        key={boss.id}
                                                        title={boss.name}
                                                        sessionKey={`event_boss_expanded_${raid.id}_${boss.id}`}
                                                        className="border-amber-600"
                                                        headerClassName="hover:bg-amber-600/10"
                                                        bodyClassName="border-amber-600"
                                                    >
                                                        {boss.images?.length > 0 && (
                                                            <div className="mb-4 flex items-center justify-center gap-3">
                                                                {boss.images.map((url, i) => (
                                                                    <img
                                                                        key={i}
                                                                        src={url}
                                                                        alt={`${boss.name} strategy ${i + 1}`}
                                                                        className="rounded-lg border border-amber-600/30"
                                                                    />
                                                                ))}
                                                            </div>
                                                        )}
                                                        {boss.notes ? (
                                                            <FormattedMarkdown>{boss.notes}</FormattedMarkdown>
                                                        ) : (
                                                            !boss.images?.length && (
                                                                <p className="italic text-gray-500">
                                                                    No strategy notes yet.
                                                                </p>
                                                            )
                                                        )}
                                                    </Collapsible>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </Deferred>
                        </div>
                    )}
                </div>
            </div>
        </Master>
    );
}
