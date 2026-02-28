import { useEffect, useMemo, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import TextInput from "@/Components/TextInput";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";

// ─── Filter components ────────────────────────────────────────────────────────

function SearchInput({ value, onChange, placeholder = "Search by name...", dusk }) {
    return (
        <div className="relative">
            <Icon
                icon="search"
                style="solid"
                className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"
            />
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                dusk={dusk}
                className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            {value && (
                <button
                    onClick={() => onChange("")}
                    dusk="clear-character-name-search"
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                >
                    <Icon icon="times" style="solid" />
                </button>
            )}
        </div>
    );
}

function FilterDropdown({ label, options, selected, onChange, dusk }) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const toggleOption = (id) => {
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);
    };

    const selectAll = () => onChange(options.map((o) => o.id));
    const selectNone = () => onChange([]);

    const count = selected.length;
    const total = options.length;
    const buttonText =
        count === 0 || count === total
            ? `All ${label}`
            : count === 1
              ? `1 ${label.slice(0, -1)}`
              : `${count} ${label}`;

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                dusk={dusk}
                className="flex w-full items-center justify-between rounded border border-amber-600 bg-brown-800 px-4 py-2 text-left text-white transition-colors hover:bg-brown-700"
            >
                <span className="truncate text-sm">{buttonText}</span>
                <Icon
                    icon="chevron-down"
                    className={`ml-2 shrink-0 text-amber-500 transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded border border-amber-600 bg-brown-800 shadow-lg">
                    <div className="flex border-b border-brown-700">
                        <button
                            onClick={selectAll}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            className="flex-1 border-l border-brown-700 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700"
                        >
                            None
                        </button>
                    </div>
                    <div className="py-1">
                        {options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-brown-700"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(option.id)}
                                    onChange={() => toggleOption(option.id)}
                                    className="h-4 w-4 rounded border-amber-600 bg-brown-900 text-amber-600 focus:ring-amber-500 focus:ring-offset-brown-800"
                                />
                                <span className="text-sm text-white">{option.name}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function DateFilterButton({ label, value, onChange, dusk }) {
    const [isOpen, setIsOpen] = useState(false);
    const [draft, setDraft] = useState(value);

    const open = () => {
        setDraft(value);
        setIsOpen(true);
    };

    const close = () => setIsOpen(false);

    const apply = () => {
        onChange(draft);
        close();
    };

    const clear = () => {
        onChange("");
        close();
    };

    return (
        <>
            <button
                onClick={open}
                dusk={dusk}
                className={`flex w-full items-center justify-between rounded border px-4 py-2 text-left text-sm transition-colors hover:bg-brown-700 ${value ? "border-amber-500 bg-brown-800 text-white" : "border-amber-600 bg-brown-800 text-gray-400"}`}
            >
                <span className="flex items-center gap-2 truncate">
                    <Icon icon="calendar" style="regular" className="shrink-0 text-amber-500" />
                    {value ? `${label}: ${value}` : label}
                </span>
                {value && (
                    <span className="ml-2 shrink-0 rounded-full bg-amber-600 px-1.5 py-0.5 text-xs text-white">
                        set
                    </span>
                )}
            </button>

            <Modal show={isOpen} onClose={close} maxWidth="sm">
                <div className="p-6">
                    <h2 className="mb-1 text-lg font-bold text-white">{label} date</h2>
                    <p className="mb-4 text-sm text-gray-400">Leave blank to show all available dates.</p>
                    <TextInput
                        type="date"
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        className="block w-full bg-brown-800/50 text-white [color-scheme:dark]"
                    />
                    <div className="mt-6 flex justify-between gap-3">
                        <button
                            type="button"
                            onClick={clear}
                            className="inline-flex items-center gap-2 rounded-md border border-gray-500 bg-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-600"
                        >
                            <Icon icon="times" style="solid" />
                            Clear
                        </button>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={close}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-brown-600"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={apply}
                                className="inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}

// ─── Matrix components ────────────────────────────────────────────────────────

function MatrixSkeleton() {
    const fakeRows = Array.from({ length: 8 });
    const fakeCols = Array.from({ length: 10 });

    return (
        <div dusk="matrix-skeleton" className="animate-pulse overflow-x-auto">
            <table className="w-full min-w-max border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        <th className="px-4 py-3">
                            <div className="h-4 w-32 rounded bg-brown-700" />
                        </th>
                        <th className="px-4 py-3">
                            <div className="h-4 w-12 rounded bg-brown-700" />
                        </th>
                        {fakeCols.map((_, i) => (
                            <th key={i} className="px-3 py-3">
                                <div className="mx-auto h-4 w-10 rounded bg-brown-700" />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-2">
                                <div className="h-4 w-28 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-2">
                                <div className="mx-auto h-4 w-12 rounded bg-brown-700" />
                            </td>
                            {fakeCols.map((_, j) => (
                                <td key={j} className="px-3 py-2">
                                    <div className="mx-auto h-4 w-4 rounded bg-brown-700" />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function AttendanceCell({ value }) {
    if (value === 1) {
        return <Icon dusk="presence-present" icon="check" style="solid" className="text-green-500" />;
    }

    if (value === 2) {
        return <Icon dusk="presence-late" icon="couch" style="regular" className="text-amber-500" />;
    }

    if (value === 0) {
        return <Icon dusk="presence-absent" icon="circle" style="regular" className="text-red-500" />;
    }

    return null;
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
        <div dusk="matrix-table" className="overflow-x-auto">
            <table className="w-full min-w-max border-collapse">
                <thead className="border-b border-amber-600">
                    <tr>
                        <th className="w-40 bg-brown-900 px-4 py-3 text-left text-sm font-semibold text-amber-500 md:sticky md:left-0 md:z-20">
                            Name
                        </th>
                        <th className="bg-brown-900 px-4 py-3 text-right text-sm font-semibold text-amber-500 lg:sticky lg:left-40 lg:z-20">
                            %
                        </th>
                        {raids.map((raid) => (
                            <th
                                key={raid.code}
                                className="whitespace-nowrap px-3 py-3 text-center text-sm font-semibold text-amber-500"
                            >
                                <p>{raid.dayOfWeek}</p>
                                <p>{raid.date}</p>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {rows.map((row) => (
                        <tr key={row.name} className="transition-colors hover:bg-brown-800/50">
                            <td
                                dusk="character-name"
                                className="w-40 bg-brown-900 px-4 py-2 text-sm font-medium text-white md:sticky md:left-0 md:z-10"
                            >
                                {row.playable_class && (
                                    <img src={row.playable_class.icon_url} alt={row.playable_class.name} className="mr-2 rounded-sm inline-block h-4 w-4" />
                                )}
                                {row.name}
                            </td>
                            <td className="whitespace-nowrap bg-brown-900 px-4 py-2 text-right text-sm text-gray-300 lg:sticky lg:left-40 lg:z-10">
                                {row.percentage.toFixed(2)}%
                            </td>
                            {row.attendance.map((value, idx) => (
                                <td key={idx} className="px-3 py-2 text-center">
                                    <AttendanceCell value={value} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Matrix({ matrix, ranks, zones, guildTags, filters }) {
    // ── Client-side filter state (no server reload) ──────────────────────────
    const [characterName, setCharacterName] = useState("");
    // null = "all selected" (initial state before matrix loads or after explicit reset)
    const [selectedClassIds, setSelectedClassIds] = useState(null);
    const [selectedRankIds, setSelectedRankIds] = useState(() => ranks.map((r) => r.id));

    const availableClasses = useMemo(() => {
        if (!matrix?.rows) return [];
        const seen = new Set();
        const result = [];
        for (const row of matrix.rows) {
            const cls = row.playable_class;
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
        filters.zone_ids?.length ? filters.zone_ids : zones.map((z) => z.id),
    );
    const [selectedGuildTagIds, setSelectedGuildTagIds] = useState(() =>
        filters.guild_tag_ids?.length ? filters.guild_tag_ids : guildTags.map((g) => g.id),
    );
    const [sinceDate, setSinceDate] = useState(filters.since_date ?? "");
    const [beforeDate, setBeforeDate] = useState(filters.before_date ?? "");

    const [isReloading, setIsReloading] = useState(false);

    // ── Server-side reload ───────────────────────────────────────────────────
    const buildServerFilters = () => ({
        zone_ids: selectedZoneIds,
        guild_tag_ids: selectedGuildTagIds,
        since_date: sinceDate || null,
        before_date: beforeDate || null,
    });

    const reloadMatrix = (filterData) => {
        setIsReloading(true);
        router.reload({
            only: ["matrix"],
            data: filterData,
            preserveState: true,
            onFinish: () => setIsReloading(false),
        });
    };

    // Trigger reload when dropdown filters change (skip initial mount)
    const isMounted = useRef(false);
    useEffect(() => {
        if (!isMounted.current) {
            isMounted.current = true;
            return;
        }
        reloadMatrix(buildServerFilters());
    }, [selectedZoneIds, selectedGuildTagIds]);

    // Trigger reload when date filters change (skip initial mount)
    const datesInitialized = useRef(false);
    useEffect(() => {
        if (!datesInitialized.current) {
            datesInitialized.current = true;
            return;
        }
        reloadMatrix(buildServerFilters());
    }, [sinceDate, beforeDate]);

    // ── Client-side row filtering ────────────────────────────────────────────
    const filteredRows = (matrix?.rows ?? [])
        .filter((row) => !characterName || normaliseCharacterName(row.name).includes(normaliseCharacterName(characterName)))
        .filter((row) => selectedRankIds.includes(row.rank_id))
        .filter((row) => selectedClassIds === null || selectedClassIds.includes(row.playable_class?.id ?? null));

    const showSkeleton = isReloading || !matrix;

    return (
        <Master title="Attendance Matrix">
            <SharedHeader title="Attendance Matrix" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Filter controls */}
                    <div className="mb-6 space-y-3">
                        {/* Row 1: Client-side filters */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <SearchInput
                                value={characterName}
                                onChange={setCharacterName}
                                placeholder="Search by name…"
                                dusk="filter-character-name"
                            />
                            <FilterDropdown
                                label="Ranks"
                                options={ranks}
                                selected={selectedRankIds}
                                onChange={setSelectedRankIds}
                                dusk="filter-rank"
                            />
                            <FilterDropdown
                                label="Classes"
                                options={availableClasses}
                                selected={effectiveClassIds}
                                onChange={setSelectedClassIds}
                                dusk="filter-class"
                            />
                        </div>

                        {/* Row 2: Server-side filters */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <FilterDropdown
                                label="Zones"
                                options={zones}
                                selected={selectedZoneIds}
                                onChange={setSelectedZoneIds}
                                dusk="filter-zone"
                            />
                            <FilterDropdown
                                label="Guild Tags"
                                options={guildTags}
                                selected={selectedGuildTagIds}
                                onChange={setSelectedGuildTagIds}
                                dusk="filter-guild-tag"
                            />
                            <DateFilterButton
                                label="Before"
                                value={beforeDate}
                                onChange={setBeforeDate}
                                dusk="filter-before-date"
                            />
                            <DateFilterButton
                                label="After"
                                value={sinceDate}
                                onChange={setSinceDate}
                                dusk="filter-since-date"
                            />
                        </div>
                    </div>

                    {/* Matrix */}
                    {showSkeleton ? (
                        <MatrixSkeleton />
                    ) : (
                        <MatrixTable key={filteredRows.length === 0 ? "empty" : "data"} raids={matrix?.raids ?? []} rows={filteredRows} />
                    )}
                </div>
            </div>
        </Master>
    );
}
