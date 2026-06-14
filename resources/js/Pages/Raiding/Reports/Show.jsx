import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import PageContainer from "@/Components/PageContainer";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";
import RankLabel from "@/Components/GuildRanks/RankLabel";
import LinkedReports from "@/Components/Raiding/Reports/LinkedReports";
import LootCouncillors from "@/Components/Raiding/Reports/LootCouncillors";
import { Can } from "@/Components/Authorizable";
import { MetaItem } from "@/Components/MetaCard";
import EmptyState from "@/Components/EmptyState";
import WarcraftLogsLogo from "@/Components/WarcraftLogs/Logo";

function ViewOnWarcraftLogsLink({ code, children }) {
    return (
        <Tooltip text="View on Warcraft Logs" position="top">
            <a
                href={`https://fresh.warcraftlogs.com/reports/${code}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20"
            >
                <WarcraftLogsLogo className="h-4 w-4" />
                {children}
            </a>
        </Tooltip>
    );
}

function CharactersTable({ characters }) {
    if (!characters || characters.length === 0) {
        return <EmptyState icon="users" message="No characters recorded for this report." />;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600">
                    <tr>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Character</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Rank</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Class</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Race</th>
                        <Can permission="view-attendance">
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Attendance</th>
                        </Can>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {characters.map((character) => (
                        <tr key={character.id} className="transition-colors hover:bg-brown-800/50">
                            <td className="px-4 py-3">
                                <span className="text-sm font-medium text-white">{character.name}</span>
                                {character.is_main && (
                                    <span className="ml-2 rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                        Main
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-300">
                                {character.rank ? <RankLabel rank={character.rank} /> : "—"}
                            </td>
                            <td className="flex flex-row items-center gap-2 px-4 py-3 text-sm text-gray-300">
                                <img
                                    src={character.playable_class?.icon_url}
                                    alt={character.playable_class?.name}
                                    className="h-4 w-4"
                                />
                                {character.playable_class?.name ?? "—"}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-300">{character.playable_race?.name ?? "—"}</td>
                            <Can permission="view-attendance">
                                <td className="px-4 py-3">
                                    <Link
                                        href={route("raiding.attendance.matrix", { character: character.id })}
                                        className="text-sm text-amber-400 hover:text-amber-300 hover:underline"
                                    >
                                        View attendance
                                    </Link>
                                </td>
                            </Can>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ report, nearbyReports, impactedReports }) {
    const data = report.data;
    const startDate = new Date(data.start_time);
    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
    const formattedDate = formatDate(data.start_time);
    const duration = formatDuration({ seconds: data.duration });
    const presentCharacters = (data.characters ?? []).filter((c) => c.pivot?.presence === 1);

    return (
        <Master title={data.title}>
            <SharedHeader title={data.title} backgroundClass="bg-illidan" />

            <PageContainer>
                {/* Back link */}
                <div className="mb-6">
                    <Link
                        href={route("raiding.reports.index")}
                        className="inline-flex items-center gap-2 text-sm text-amber-400 hover:text-amber-300 hover:underline"
                    >
                        <Icon icon="arrow-left" style="solid" />
                        Back to Reports
                    </Link>
                </div>

                {/* Report metadata card */}
                <div className="mb-8 rounded border border-amber-600/30 bg-brown-800/50 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex flex-1 flex-wrap gap-x-8 gap-y-3">
                            <MetaItem icon="calendar">
                                <span>
                                    {dayOfWeek}, <span className="md:hidden">{formattedDate.short}</span>
                                    <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                    <span className="hidden lg:inline">{formattedDate.long}</span>
                                </span>
                            </MetaItem>
                            <MetaItem icon="clock">{duration}</MetaItem>
                            {data.zone?.name && <MetaItem icon="map-marker-alt">{data.zone.name}</MetaItem>}
                            {data.guild_tag?.name && <MetaItem icon="tag">{data.guild_tag.name}</MetaItem>}
                        </div>
                        {data.code && (
                            <div className="shrink-0">
                                <ViewOnWarcraftLogsLink code={data.code}>View on Warcraft Logs</ViewOnWarcraftLogsLink>
                            </div>
                        )}
                    </div>
                </div>

                {/* Characters */}
                <h2 className="mb-4 text-xl font-semibold text-white">
                    Attendance
                    {data.characters?.length > 0 && (
                        <span className="ml-2 text-base font-normal text-gray-400">({data.characters.length})</span>
                    )}
                </h2>
                <div className="rounded border border-amber-600/30">
                    <CharactersTable characters={presentCharacters} />
                </div>

                {/* Loot councillors */}
                <LootCouncillors reportId={data.id} characters={data.characters} />

                {/* Linked reports */}
                <LinkedReports
                    currentReport={data}
                    nearbyReports={nearbyReports}
                    impactedReports={impactedReports}
                    referenceDate={data.start_time}
                />
            </PageContainer>
        </Master>
    );
}
