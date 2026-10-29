import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import SectionNav from "@/Components/Navigation/SectionNav";
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
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from "@/Components/Table";

function ViewOnWarcraftLogsLink({ code, children }) {
    return (
        <Tooltip body="View on Warcraft Logs" position="top">
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

function CharactersTable({ characters, gameVersion }) {
    if (!characters || characters.length === 0) {
        return <EmptyState icon="users" message="No characters recorded for this report." />;
    }

    return (
        <Table>
            <TableHead>
                <tr>
                    <TableHeader className="text-left">Character</TableHeader>
                    <TableHeader className="text-left">Rank</TableHeader>
                    <TableHeader className="text-left">Class</TableHeader>
                    <TableHeader className="text-left">Race</TableHeader>
                    <Can permission="view-attendance">
                        <TableHeader className="text-left">Attendance</TableHeader>
                    </Can>
                </tr>
            </TableHead>
            <TableBody>
                {characters.map((character) => (
                    <TableRow key={character.id}>
                        <TableCell>
                            <span className="text-sm font-medium text-white">{character.name}</span>
                            {character.is_main && (
                                <span className="ml-2 rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                    Main
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="text-sm text-gray-300">
                            {character.rank ? <RankLabel rank={character.rank} /> : "—"}
                        </TableCell>
                        <TableCell className="flex flex-row items-center gap-2 text-sm text-gray-300">
                            <img
                                src={character.playable_class?.icon_url}
                                alt={character.playable_class?.name}
                                className="h-4 w-4"
                            />
                            {character.playable_class?.name ?? "—"}
                        </TableCell>
                        <TableCell className="text-sm text-gray-300">{character.playable_race?.name ?? "—"}</TableCell>
                        <Can permission="view-attendance">
                            <TableCell>
                                <Link
                                    href={route("raiding.attendance.matrix", {
                                        character: character.id,
                                        game_version: gameVersion,
                                    })}
                                    className="text-sm text-amber-400 hover:text-amber-300 hover:underline"
                                >
                                    View attendance
                                </Link>
                            </TableCell>
                        </Can>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
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
            <SectionNav section="raiding" />

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
                    <CharactersTable characters={presentCharacters} gameVersion={data.game_version} />
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
