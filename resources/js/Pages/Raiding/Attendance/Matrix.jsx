import { useEffect, useMemo, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import SectionNav from "@/Components/Navigation/SectionNav";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import { TableHeader } from "@/Components/Table";
import DateFilterButton from "@/Components/DateFilterButton";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";
import Tooltip from "@/Components/Tooltip";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import { decodeFilter, encodeFilter, encodeFilterAgainstDefault } from "@/Helpers/EncodeFilter";
import RankLabel from "@/Components/GuildRanks/RankLabel";
import FilterDropdown from "@/Components/FilterDropdown";
import SearchInput from "@/Components/Search/SearchInput";
import ToggleFilter from "@/Components/ToggleFilter";
import { useResetQueryOnGameVersionSwitch } from "@/Hooks/useGameVersionSwitch";

// ─── Matrix components ────────────────────────────────────────────────────────

/**
 * Formats the calendar date of an ISO timestamp as written, ignoring the viewer's timezone.
 */
function formatRaidDate(isoDateTime, options) {
    const [year, month, day] = isoDateTime.slice(0, 10).split("-").map(Number);
    return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString("en-GB", { ...options, timeZone: "UTC" });
}

function MatrixSkeleton() {
    const fakeRows = Array.from({ length: 8 });
    const fakeCols = Array.from({ length: 10 });

    return (
        <div className="animate-pulse overflow-x-auto">
            <table className="w-full min-w-max border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        <th className="px-4 py-3">
                            <div className="bg-brown-700 h-4 w-32 rounded" />
                        </th>
                        <th className="px-4 py-3">
                            <div className="bg-brown-700 h-4 w-12 rounded" />
                        </th>
                        {fakeCols.map((_, i) => (
                            <th key={i} className="px-3 py-3">
                                <div className="bg-brown-700 mx-auto h-4 w-10 rounded" />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-amber-600 divide-y">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-2">
                                <div className="bg-brown-700 h-4 w-28 rounded" />
                            </td>
                            <td className="px-4 py-2">
                                <div className="bg-brown-700 mx-auto h-4 w-12 rounded" />
                            </td>
                            {fakeCols.map((_, j) => (
                                <td key={j} className="px-3 py-2">
                                    <div className="bg-brown-700 mx-auto h-4 w-4 rounded" />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function AttendanceCell({ value, names, plannedAbsence }) {
    if (value === 0 && plannedAbsence) {
        return (
            <Tooltip
                body={
                    <div className="flex flex-col gap-1">
                        <span className="font-semibold">{plannedAbsence.reason.label}</span>
                        {plannedAbsence.notes && <FormattedMarkdown>{plannedAbsence.notes}</FormattedMarkdown>}
                    </div>
                }
                position="bottom"
            >
                <Icon icon={plannedAbsence.reason.icon} style="solid" className="text-sky-400" aria-label={plannedAbsence.reason.label} />
            </Tooltip>
        );
    }

    let icon = null;

    if (value === 1) {
        icon = <Icon icon="check" style="solid" className="text-green-500" />;
        return (
            <Tooltip body={names?.length > 0 ? names.join(", ") : undefined} position="bottom">
                {icon}
            </Tooltip>
        );
    } else if (value === 2) {
        icon = <Icon icon="couch" style="regular" className="text-amber-500" />;
    } else if (value === 0) {
        icon = <Icon icon="circle" style="regular" className="text-red-500" />;
    }

    return icon;
}

function MatrixTable({ raids, rows }) {
    if (rows.length === 0) {
        return (
            <div className="py-16 text-center text-gray-400">
                <Icon icon="table" style="solid" className="mb-4 text-4xl" />
                <p>No attendance data available.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto overflow-y-hidden">
            <table className="min-w-max table-auto border-collapse">
                <thead className="border-b border-amber-600">
                    <tr>
                        <TableHeader className="bg-brown-900 text-left md:sticky md:left-0 md:z-20">Name</TableHeader>
                        <TableHeader className="bg-brown-900 hidden text-left md:sticky md:left-[max-content] md:z-20 md:table-cell">
                            Rank
                        </TableHeader>
                        <TableHeader className="bg-brown-900 text-right lg:sticky lg:z-20">%</TableHeader>
                        {raids.map((raid) => (
                            <TableHeader key={raid.id} className="px-3 text-center whitespace-nowrap">
                                <Tooltip body={raid.zone_name} position="bottom">
                                    <p>{formatRaidDate(raid.start_time, { weekday: "short" })}</p>
                                    <p>{formatRaidDate(raid.start_time, { day: "2-digit", month: "2-digit" })}</p>
                                </Tooltip>
                            </TableHeader>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-amber-600 divide-y">
                    {rows.map((row) => {
                        const { character } = row;
                        return (
                            <tr key={character.id} className="hover:bg-brown-800/50 transition-colors">
                                <td className="bg-brown-900 px-4 py-2 text-sm font-medium text-white md:sticky md:z-10">
                                    <div className="flex flex-col gap-1 lg:flex-row lg:gap-2">
                                        <p className="flex grow-0 flex-row items-center font-semibold">
                                            {character.playable_class && (
                                                <img
                                                    src={character.playable_class.icon_url}
                                                    alt={character.playable_class.name}
                                                    className="mr-2 hidden inline-block h-4 w-4 rounded-xs md:inline-flex"
                                                />
                                            )}
                                            {character.name}
                                        </p>
                                    </div>
                                </td>
                                <td className="bg-brown-900 hidden px-4 py-2 text-right text-sm whitespace-nowrap text-gray-300 md:table-cell lg:sticky lg:z-10">
                                    {character.rank && <RankLabel rank={character.rank} />}
                                </td>
                                <td className="bg-brown-900 px-4 py-2 text-right text-sm whitespace-nowrap text-gray-300 lg:sticky lg:z-10">
                                    <span className="hidden md:inline">{row.percentage.toFixed(2)}%</span>
                                    <span className="inline md:hidden">{row.percentage.toFixed(0)}%</span>
                                </td>
                                {row.attendance.map((value, idx) => (
                                    <td key={idx} className="px-3 py-2 text-center">
                                        <AttendanceCell
                                            value={value}
                                            names={row.attendance_names?.[idx] ?? []}
                                            plannedAbsence={row.planned_absences[idx]}
                                        />
                                    </td>
                                ))}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Matrix({ hasAttendance, ...props }) {
    useResetQueryOnGameVersionSwitch();

    return (
        <Master title="Attendance Matrix">
            <SharedHeader title="Attendance Matrix" backgroundClass="bg-illidan" />
            <SectionNav section="raiding" />
            <PageContainer>
                {hasAttendance ? (
                    <MatrixContent {...props} />
                ) : (
                    <EmptyState icon="table" message="Attendance data is not available for this game version." />
                )}
            </PageContainer>
        </Master>
    );
}

function MatrixContent({ matrix, ranks: { data: ranks }, zones: { data: zones }, guildTags: { data: guildTags }, filters, earliestDate }) {
    // ── Client-side filter state (no server reload) ──────────────────────────
    const [characterName, setCharacterName] = useState(filters.character?.name ?? "");
    const [characterId, setCharacterId] = useState(filters.character?.id ?? null);

    // Updates the name search and drops the server-side character filter.
    const handleCharacterNameChange = (name) => {
        setCharacterName(name);
        setCharacterId(null);
    };
    // null = "all selected" (initial state before matrix loads or after explicit reset)
    const [selectedClassIds, setSelectedClassIds] = useState(null);
    const defaultRankIds = ranks.filter((r) => r.count_attendance).map((r) => r.id);
    const [selectedRankIds, setSelectedRankIds] = useState(() => decodeFilter(filters.rank_ids, defaultRankIds));

    const availableClasses = useMemo(() => {
        if (!matrix?.rows) return [];
        const seen = new Set();
        const result = [];
        for (const row of matrix.rows) {
            const cls = row.character.playable_class;
            if (cls != null && !seen.has(cls.id)) {
                seen.add(cls.id);
                result.push(cls);
            }
        }
        return result.sort((a, b) => (a.name ?? "").localeCompare(b.name ?? ""));
    }, [matrix]);

    const effectiveClassIds = selectedClassIds ?? availableClasses.map((c) => c.id);

    // ── Server-side filter state (trigger partial reload) ────────────────────
    const [selectedZoneIds, setSelectedZoneIds] = useState(() =>
        decodeFilter(
            filters.zone_ids,
            zones.map((z) => z.id),
        ),
    );
    const defaultGuildTagIds = guildTags.filter((g) => g.count_attendance).map((g) => g.id);
    const [selectedGuildTagIds, setSelectedGuildTagIds] = useState(() =>
        decodeFilter(filters.guild_tag_ids, defaultGuildTagIds),
    );
    const [sinceDate, setSinceDate] = useState(filters.since_date ?? "");
    const [beforeDate, setBeforeDate] = useState(filters.before_date ?? "");
    const [includeLinkedCharacters, setIncludeLinkedCharacters] = useState(filters.combine_linked_characters);

    const [isReloading, setIsReloading] = useState(false);
    const latestReloadId = useRef(0);

    // ── Server-side reload ───────────────────────────────────────────────────
    const buildServerFilters = () => ({
        rank_ids: encodeFilterAgainstDefault(selectedRankIds, defaultRankIds),
        zone_ids: encodeFilter(selectedZoneIds, zones),
        guild_tag_ids: encodeFilterAgainstDefault(selectedGuildTagIds, defaultGuildTagIds),
        since_date: sinceDate || undefined,
        before_date: beforeDate || undefined,
        combine_linked_characters: includeLinkedCharacters ? undefined : 0,
        character: characterId ?? undefined,
    });

    const reloadMatrix = (filterData) => {
        router.cancelAll({ sync: false, prefetch: false });
        const reloadId = ++latestReloadId.current;
        setIsReloading(true);
        router.reload({
            only: ["matrix"],
            data: filterData,
            preserveState: true,
            onFinish: () => {
                if (reloadId === latestReloadId.current) {
                    setIsReloading(false);
                }
            },
        });
    };

    // Trigger reload when dropdown filters change (skip initial mount)
    const isMounted = useRef(false);
    const hasEmptyFilter =
        selectedRankIds.length === 0 || selectedZoneIds.length === 0 || selectedGuildTagIds.length === 0;

    useEffect(() => {
        if (!isMounted.current) {
            isMounted.current = true;
            return;
        }
        if (hasEmptyFilter) return;
        reloadMatrix(buildServerFilters());
    }, [selectedZoneIds, selectedGuildTagIds, includeLinkedCharacters, selectedRankIds, characterId]);

    // Trigger reload when date filters change (skip initial mount)
    const datesInitialized = useRef(false);
    useEffect(() => {
        if (!datesInitialized.current) {
            datesInitialized.current = true;
            return;
        }
        if (hasEmptyFilter) return;
        reloadMatrix(buildServerFilters());
    }, [sinceDate, beforeDate]);

    // ── Client-side row filtering ────────────────────────────────────────────
    const filteredRows = useMemo(() => {
        const searchTerm = characterName ? normaliseCharacterName(characterName) : null;

        return (matrix?.rows ?? []).filter(
            (row) =>
                (!searchTerm || normaliseCharacterName(row.character.name).includes(searchTerm)) &&
                (selectedClassIds === null || selectedClassIds.includes(row.character.playable_class?.id ?? null)),
        );
    }, [matrix, characterName, selectedClassIds]);

    const showSkeleton = !hasEmptyFilter && (isReloading || !matrix);

    return (
        <>
                {/* Filter controls */}
                <div className="mb-6 space-y-3">
                    {/* Row 1: Client-side filters */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <SearchInput value={characterName} onChange={handleCharacterNameChange} placeholder="Search by name…" />
                        <FilterDropdown
                            label={{ singular: "Rank", plural: "Ranks" }}
                            options={ranks}
                            selected={selectedRankIds}
                            onChange={setSelectedRankIds}
                        />
                        <FilterDropdown
                            label={{ singular: "Class", plural: "Classes" }}
                            options={availableClasses}
                            selected={effectiveClassIds}
                            onChange={(ids) => setSelectedClassIds(ids.length === availableClasses.length ? null : ids)}
                        />
                    </div>

                    {/* Row 2: Server-side filters */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <FilterDropdown
                            label={{ singular: "Zone", plural: "Zones" }}
                            options={zones}
                            selected={selectedZoneIds}
                            onChange={setSelectedZoneIds}
                        />
                        <FilterDropdown
                            label={{ singular: "Guild Tag", plural: "Guild Tags" }}
                            options={guildTags}
                            selected={selectedGuildTagIds}
                            onChange={setSelectedGuildTagIds}
                        />
                        <DateFilterButton
                            label="Before"
                            value={beforeDate}
                            onChange={setBeforeDate}
                            min={earliestDate}
                        />
                        <DateFilterButton label="After" value={sinceDate} onChange={setSinceDate} min={earliestDate} />
                        <ToggleFilter
                            label="Combine linked characters"
                            value={includeLinkedCharacters}
                            onChange={setIncludeLinkedCharacters}
                        />
                    </div>
                </div>

                {/* Matrix */}
                {showSkeleton ? (
                    <MatrixSkeleton />
                ) : hasEmptyFilter ? (
                    <MatrixTable key="empty" raids={[]} rows={[]} />
                ) : (
                    <MatrixTable
                        key={filteredRows.length === 0 ? "empty" : "data"}
                        raids={matrix?.columns ?? []}
                        rows={filteredRows}
                    />
                )}
        </>
    );
}
