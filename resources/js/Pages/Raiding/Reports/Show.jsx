import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";
import GuildRankLabel from "@/Components/GuildRankLabel";
import LinkedRaidReports from "@/Components/LinkedRaidReports";
import RaidReportLootCouncillors from "@/Components/RaidReportLootCouncillors";
import usePermission from "@/Hooks/Permissions";

function ViewOnWarcraftLogsLink({ code, children }) {
    return (
        <Tooltip text="View on Warcraft Logs" position="top">
            <a
                href={`https://fresh.warcraftlogs.com/reports/${code}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 553 552"
                    fill="currentColor"
                    className="h-4 w-4"
                    aria-hidden="true"
                >
                    <path d="M291.31,249.46l-23.42,15.86-26.02-87.8-44.1,133.45-11.73-38.41h-53.63c-.1-45.61,22.73-88.84,61.02-115.06,45.52-31.17,105.41-34.05,153.82-7.52,45.72,25.05,73.9,73.45,73.47,123.06l-56.6-.45-16.14,46.54-13.65-44.39-26.59,27.54-16.43-52.81Z" />
                    <path d="M419.94,291.71c-5.47,56.44-41.11,102.79-94.61,120.37-69.49,22.84-148.69-4.95-179.62-73.49-6.6-15.08-10.71-29.94-12.35-46.82l42.11-.09,22.68,67.48,43.16-131.32,18.33,61.97,23.69-16.54,17.79,56.99,26.07-27.34,18.89,65.49,28.17-76.67,45.69-.03Z" />
                    <path d="M393.29,86.56c-71.53-44.07-161.83-44.13-233.19.01l-36.02-39.37C163.11,20.75,206.93,6.28,253.46,1.47c15.63-.73,30.45-.72,46.09-.02,46.59,4.73,90.45,19.24,129.65,45.74l-35.91,39.37Z" />
                    <path d="M81.09,427.95l-19.28,19.36C24.79,401.45,4.8,346.01,1.51,287.69c-.46-8.09-.92-15.32.04-23.38,2.86-50.16,17.43-97.88,46.32-140.9l39.45,35.94c-41.3,66.92-44.2,150.44-7.37,220.19-12.79,1.33-24.16,5.33-33,14.23l34.14,34.18Z" />
                    <path d="M472.33,428.15l33.89-34.36c-8.6-8.67-19.79-12.68-32.88-14.14,36.51-69.5,34.25-152.68-7.33-220.14l39.29-36.16c27.7,40.89,42.51,87.26,46.18,136.03.77,11.38.8,21.9-.04,33.27-4.11,56.77-24.24,110.4-60.02,154.71l-19.1-19.2Z" />
                    <path d="M428.46,471.65l19.47,19.12c-43.25,35.02-95.72,55.01-151.33,59.74-13.72.76-26.49.77-40.2-.01-55.5-4.78-107.77-24.74-151.01-59.65l19.13-19.49,34.16,34.19c9.21-9.01,12.85-20.07,14.27-32.97,65.39,34.49,142.63,34.13,207.4,0,1.25,12.93,5.05,23.8,14.04,33.07l34.05-34Z" />
                    <path d="M413.49,196.66c-13.24-23.86-32.99-43.43-57.8-57.39l87.48-95.19,83.7-18.45-18.41,83.74-94.97,87.29Z" />
                    <path d="M197.45,139.1c-24.86,13.79-44.14,32.9-57.97,57.26L44.8,109.35,26.38,25.65l83.71,18.41,87.36,95.04Z" />
                    <path d="M124.63,460.81l-71.19,71.13-32.83-32.65,71.19-71.27-33.49-33.62c9.65-6.48,20.98-7.82,32.74-7.24l74.07,74.04c1.16,11.16-.45,22.84-6.9,33.12l-33.6-33.52Z" />
                    <path d="M500.03,532.15l-71.39-71.34-33.67,33.57c-6.32-10.14-7.66-21.26-7.11-32.84l74.26-74.27c11.62-.66,22.66.8,32.86,7.06l-33.54,33.71,71.29,71.32-32.7,32.8Z" />
                    <path d="M159.67,436.81l-43.95-43.95,30.76-33.62c11.29,19.47,27.12,34.89,46.59,46.93l-33.4,30.64Z" />
                    <path d="M393.53,436.84l-33.28-30.67c19.2-11.88,34.65-26.96,46.65-46.75l30.65,33.42-44.02,43.99Z" />
                </svg>
                {children}
            </a>
        </Tooltip>
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

function CharactersTable({ characters }) {
    if (!characters || characters.length === 0) {
        return (
            <div className="py-12 text-center text-gray-400">
                <Icon icon="users" style="solid" className="mb-3 text-3xl" />
                <p>No characters recorded for this report.</p>
            </div>
        );
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
                        {usePermission("view-attendance") && (
                            <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Attendance</th>
                        )}
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
                                {character.rank ? <GuildRankLabel rank={character.rank} /> : "—"}
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
                            {usePermission("view-attendance") && (
                                <td className="px-4 py-3">
                                    <Link
                                        href={route("raiding.attendance.matrix", { character: character.id })}
                                        className="text-sm text-amber-400 hover:text-amber-300 hover:underline"
                                    >
                                        View attendance
                                    </Link>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ report, nearbyReports, impactedReports, canManageLinks }) {
    const data = report.data;
    const startDate = new Date(data.start_time);
    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
    const formattedDate = formatDate(data.start_time);
    const duration = formatDuration({ seconds: data.duration });
    const presentCharacters = (data.characters ?? []).filter((c) => c.pivot?.presence === 1);

    return (
        <Master title={data.title}>
            <SharedHeader title={data.title} backgroundClass="bg-illidan" />

            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
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
                                <div className="flex-shrink-0">
                                    <ViewOnWarcraftLogsLink code={data.code}>
                                        View on Warcraft Logs
                                    </ViewOnWarcraftLogsLink>
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
                    <RaidReportLootCouncillors reportId={data.id} characters={data.characters} />

                    {/* Linked reports */}
                    <LinkedRaidReports
                        currentReport={data}
                        canManageLinks={canManageLinks}
                        nearbyReports={nearbyReports}
                        impactedReports={impactedReports}
                        referenceDate={data.start_time}
                    />
                </div>
            </div>
        </Master>
    );
}
