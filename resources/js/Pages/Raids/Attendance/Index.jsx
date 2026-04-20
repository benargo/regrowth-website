import { Deferred, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";

function formatAbsenceDate(isoDate) {
    if (!isoDate) return "";
    const [year, month, day] = isoDate.split("-").map(Number);
    return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        timeZone: "UTC",
    });
}

function BoxLabel({ icon, label }) {
    return (
        <p className="flex items-center gap-2 align-top text-sm text-gray-400">
            {icon && <Icon icon={icon} style="light" className="text-amber-400" />}
            <span>{label}</span>
        </p>
    );
}

function StatBox({ icon, label, value, subLabel, subText, className }) {
    return (
        <div className={`flex flex-col rounded border border-amber-600 p-4${className ? ` ${className}` : ""}`}>
            <BoxLabel icon={icon} label={label} />
            <p className="mt-1 flex-grow align-top text-3xl font-bold text-amber-400">{value ?? "–"}</p>
            {subText && <p className="mt-1 flex-grow text-xs text-gray-400">{subText}</p>}
            {subLabel && <p className="mt-2 flex-grow text-xs text-gray-500">{subLabel}</p>}
        </div>
    );
}

function PlayerChip({ player }) {
    return (
        <span className="inline-flex items-center gap-1 rounded bg-brown-800 px-2 py-1 text-xs text-white">
            {player.playable_class?.icon_url && (
                <img
                    src={player.playable_class.icon_url}
                    alt={player.playable_class.name}
                    className="inline-block h-3 w-3 rounded-sm"
                />
            )}
            {player.name}
        </span>
    );
}

function PlayerListBox({ icon, label, players }) {
    return (
        <div className="rounded border border-amber-600 p-4">
            <BoxLabel icon={icon} label={label} />
            <p className="mt-1 text-3xl font-bold text-amber-400">{players.length}</p>
            {players.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1">
                    {players.map((player) => (
                        <PlayerChip key={player.name} player={player} />
                    ))}
                </div>
            )}
        </div>
    );
}

function BenchedByTagBox({ icon, label, groups }) {
    const entries = Object.entries(groups);
    const total = entries.reduce((sum, [, players]) => sum + players.length, 0);

    return (
        <div className="rounded border border-amber-600 p-4">
            <BoxLabel icon={icon} label={label} />
            <p className="mt-1 text-3xl font-bold text-amber-400">{total}</p>
            {entries.length === 0 ? (
                <p className="mt-3 text-sm text-gray-500">No benched players this week.</p>
            ) : (
                <div className="mt-3 flex flex-col gap-2">
                    {entries.map(([tag, players]) => (
                        <div key={tag}>
                            <p className="text-xs font-semibold text-amber-300">{tag}</p>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {players.map((player) => (
                                    <PlayerChip key={player.name} player={player} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function SkeletonBox() {
    return (
        <div className="animate-pulse rounded border border-amber-600/30 p-4">
            <div className="mb-2 h-3 w-1/2 rounded bg-gray-700" />
            <div className="h-8 w-1/3 rounded bg-gray-700" />
        </div>
    );
}

function SkeletonPlayerBox() {
    return (
        <div className="animate-pulse rounded border border-amber-600/30 p-4">
            <div className="mb-2 h-3 w-1/2 rounded bg-gray-700" />
            <div className="mb-3 h-8 w-1/4 rounded bg-gray-700" />
            <div className="flex flex-wrap gap-1">
                {[...Array(6)].map((_, i) => (
                    <div key={i} className="h-5 w-16 rounded bg-gray-700" />
                ))}
            </div>
        </div>
    );
}

function UpcomingAbsencesBox({ icon, absences }) {
    return (
        <div className="flex flex-col rounded border border-amber-600 p-4">
            <BoxLabel icon={icon} label="Upcoming planned absences" />
            {absences.length === 0 ? (
                <p className="mt-3 text-sm text-gray-500">No upcoming absences.</p>
            ) : (
                <ul className="mt-3 flex flex-col gap-2">
                    {absences.map((absence) => (
                        <li key={absence.id} className="flex flex-col">
                            <span className="flex items-center gap-2 text-sm font-semibold text-amber-400">
                                {absence.character?.playable_class?.icon_url && (
                                    <img
                                        src={absence.character.playable_class.icon_url}
                                        alt={absence.character.playable_class.name}
                                        className="inline-block h-4 w-4 rounded-sm"
                                    />
                                )}
                                {absence.character?.name ?? "Unknown"}
                            </span>
                            <span className="text-xs text-gray-400">
                                {absence.end_date && absence.end_date !== absence.start_date
                                    ? `${formatAbsenceDate(absence.start_date)} – ${formatAbsenceDate(absence.end_date)}`
                                    : formatAbsenceDate(absence.start_date)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Link
                href={route("raids.absences.index")}
                className="mt-3 inline-flex items-center gap-1 text-sm text-amber-400 hover:text-amber-300"
            >
                See all <Icon icon="arrow-right" style="light" />
            </Link>
        </div>
    );
}

function HeaderRowSkeleton() {
    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <SkeletonBox />
            <SkeletonBox />
            <SkeletonBox />
        </div>
    );
}

function PlayerRowsSkeleton() {
    return (
        <>
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <SkeletonBox />
                <SkeletonPlayerBox />
                <SkeletonPlayerBox />
            </div>
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <SkeletonPlayerBox />
                <SkeletonPlayerBox />
                <SkeletonPlayerBox />
            </div>
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <SkeletonPlayerBox />
                <SkeletonBox />
            </div>
        </>
    );
}

function StatsHeaderRow({ stats, latestReportDate }) {
    const hasPreviousPhase = stats.previousPhaseAttendance !== null;

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-6">
            <StatBox
                icon="users"
                label="Total players monitored"
                value={stats.totalPlayers}
                subText={`${stats.totalMains} mains · ${stats.totalLinkedCharacters} linked ${stats.totalLinkedCharacters === 1 ? "character" : "characters"}`}
                className="col-span-2"
            />
            <StatBox
                icon="calendar"
                label="Latest report date"
                value={latestReportDate ?? "No reports yet"}
                className="col-span-2"
            />
            <StatBox
                icon="play"
                label="Average attendance this phase"
                value={stats.phaseAttendance !== null ? `${stats.phaseAttendance}%` : null}
                subLabel={stats.phaseAttendance === null ? "No raids yet this phase" : undefined}
            />
            <StatBox
                icon="step-backward"
                label="Average attendance last phase"
                value={hasPreviousPhase ? `${stats.previousPhaseAttendance}%` : null}
                subLabel={!hasPreviousPhase ? "No data available" : undefined}
            />
        </div>
    );
}

function PlayerListRows({ stats }) {
    return (
        <>
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <PlayerListBox
                    icon="star"
                    label="Players above 80% attendance"
                    players={stats.percentageGroups[">=80"] ?? []}
                />
                <PlayerListBox
                    icon="user-check"
                    label="Players between 50–80% attendance"
                    players={stats.percentageGroups["50-80"] ?? []}
                />
                <PlayerListBox
                    icon="user-check"
                    label="Players below 50% attendance"
                    players={stats.percentageGroups["<50"] ?? []}
                />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <BenchedByTagBox icon="couch" label="Players benched last week" groups={stats.benchedLastWeek} />
                <PlayerListBox icon="chart-line-down" label="Players dropping off" players={stats.droppingOff} />
                <PlayerListBox icon="chart-line" label="Players picking up" players={stats.pickingUp} />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <UpcomingAbsencesBox icon="umbrella-beach" absences={stats.upcomingAbsences} />
                <Link
                    href={route("raids.attendance.graphs.index")}
                    className="flex items-center gap-4 rounded border border-amber-600 p-4 transition-colors hover:bg-amber-600/20"
                >
                    <div className="text-center">
                        <Icon icon="chart-scatter" style="light" className="text-3xl text-amber-400" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h3 className="text-lg font-semibold">Attendance distribution</h3>
                        <p className="text-sm text-gray-400">
                            View per-player attendance spread on an interactive chart.
                        </p>
                    </div>
                </Link>
                <Link
                    href={route("raids.attendance.matrix")}
                    className="flex items-center gap-4 rounded border border-amber-600 p-4 transition-colors hover:bg-amber-600/20"
                >
                    <div className="text-center">
                        <Icon icon="table" style="light" className="text-3xl text-amber-400" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h3 className="text-lg font-semibold">Full attendance matrix</h3>
                        <p className="text-sm text-gray-400">View per-raid attendance for all tracked players.</p>
                    </div>
                </Link>
            </div>
        </>
    );
}

export default function Index({ latestReportDate, stats }) {
    return (
        <Master title="Attendance Dashboard">
            <SharedHeader title="Attendance Dashboard" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <Deferred data="stats" fallback={<HeaderRowSkeleton />}>
                        <StatsHeaderRow stats={stats} latestReportDate={latestReportDate} />
                    </Deferred>

                    <Deferred data="stats" fallback={<PlayerRowsSkeleton />}>
                        <PlayerListRows stats={stats} />
                    </Deferred>
                </div>
            </div>
        </Master>
    );
}
