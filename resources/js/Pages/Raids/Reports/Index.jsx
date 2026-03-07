import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import TextInput from "@/Components/TextInput";
import Pagination from "@/Components/Pagination";

// ─── Filter components ────────────────────────────────────────────────────────

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
    let buttonText;
    if (count === 0) buttonText = `No ${label}`;
    else if (count === total) buttonText = `All ${label}`;
    else if (count === 1) buttonText = `1 ${label.slice(0, -1)}`;
    else buttonText = `${count} ${label}`;

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
                            dusk={`${dusk}-all`}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            dusk={`${dusk}-none`}
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

function DateFilterButton({ label, value, onChange, dusk, min }) {
    const [isOpen, setIsOpen] = useState(false);
    const [draft, setDraft] = useState(value);
    const today = new Date().toISOString().split("T")[0];

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
                        min={min}
                        max={today}
                        onChange={(e) => setDraft(e.target.value)}
                        className="block w-full bg-brown-800/50 text-white [color-scheme:dark]"
                    />
                    <div className="mt-6 flex justify-between gap-3">
                        <button
                            type="button"
                            onClick={clear}
                            dusk="modal-clear-button"
                            className="inline-flex items-center gap-2 rounded-md border border-gray-500 bg-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-600"
                        >
                            <Icon icon="times" style="solid" />
                            Clear
                        </button>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={close}
                                dusk="modal-cancel-button"
                                className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-brown-600"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={apply}
                                dusk="modal-apply-button"
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

// ─── Report components ────────────────────────────────────────────────────────

function formatDuration(minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

function ReportsSkeleton() {
    const fakeRows = Array.from({ length: 8 });

    return (
        <div dusk="reports-skeleton" className="animate-pulse">
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        {["w-32", "w-48", "w-24", "w-24", "w-16"].map((w, i) => (
                            <th key={i} className="px-4 py-3 text-left">
                                <div className={`h-4 ${w} rounded bg-brown-700`} />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-3">
                                <div className="h-4 w-28 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-44 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-20 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-20 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-12 rounded bg-brown-700" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ReportsTable({ reports }) {
    if (reports.data.length === 0) {
        return (
            <div className="py-16 text-center text-gray-400">
                <Icon icon="scroll" style="solid" className="mb-4 text-4xl" />
                <p>No reports match the selected filters.</p>
            </div>
        );
    }

    return (
        <div dusk="reports-table" className="overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600">
                    <tr>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Date</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Title</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Zone</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Guild Tag</th>
                        <th className="px-4 py-3 text-right text-sm font-semibold text-amber-500">Duration</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {reports.data.map((report) => (
                        <tr key={report.code} className="transition-colors hover:bg-brown-800/50">
                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-300">
                                <p>{report.day_of_week}</p>
                                <p className="text-xs text-gray-500">{report.date}</p>
                            </td>
                            <td className="px-4 py-3 text-sm">
                                <a
                                    href={`https://www.warcraftlogs.com/reports/${report.code}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    dusk="report-link"
                                    className="font-medium text-amber-400 hover:text-amber-300 hover:underline"
                                >
                                    {report.title}
                                    <Icon icon="arrow-up-right-from-square" style="solid" className="ml-1.5 text-xs opacity-60" />
                                </a>
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-300">{report.zone_name ?? "—"}</td>
                            <td className="px-4 py-3 text-sm text-gray-300">{report.guild_tag?.name ?? "—"}</td>
                            <td className="px-4 py-3 text-right text-sm text-gray-300">
                                {formatDuration(report.duration_minutes)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Day options ──────────────────────────────────────────────────────────────

const DAYS = [
    { id: 0, name: "Sunday" },
    { id: 1, name: "Monday" },
    { id: 2, name: "Tuesday" },
    { id: 3, name: "Wednesday" },
    { id: 4, name: "Thursday" },
    { id: 5, name: "Friday" },
    { id: 6, name: "Saturday" },
];

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Index({ reports, zones, guildTags, filters, earliestDate }) {
    const [selectedZoneIds, setSelectedZoneIds] = useState(() =>
        filters.zone_ids?.length ? filters.zone_ids : zones.map((z) => z.id),
    );
    const [selectedGuildTagIds, setSelectedGuildTagIds] = useState(() =>
        filters.guild_tag_ids?.length ? filters.guild_tag_ids : guildTags.map((g) => g.id),
    );
    const [selectedDays, setSelectedDays] = useState(() =>
        filters.days?.length ? filters.days : DAYS.map((d) => d.id),
    );
    const [sinceDate, setSinceDate] = useState(filters.since_date ?? "");
    const [beforeDate, setBeforeDate] = useState(filters.before_date ?? "");

    const [isReloading, setIsReloading] = useState(false);

    const buildFilters = () => {
        const allZonesSelected = selectedZoneIds.length === zones.length;
        const allTagsSelected = selectedGuildTagIds.length === guildTags.length;
        const allDaysSelected = selectedDays.length === DAYS.length;

        return {
            zone_ids: allZonesSelected ? undefined : selectedZoneIds,
            guild_tag_ids: allTagsSelected ? undefined : selectedGuildTagIds,
            days: allDaysSelected ? undefined : selectedDays,
            since_date: sinceDate || null,
            before_date: beforeDate || null,
        };
    };

    const reloadReports = (filterData) => {
        setIsReloading(true);
        router.reload({
            only: ["reports"],
            data: filterData,
            preserveState: true,
            onFinish: () => setIsReloading(false),
        });
    };

    const isMounted = useRef(false);
    useEffect(() => {
        if (!isMounted.current) {
            isMounted.current = true;
            return;
        }
        reloadReports(buildFilters());
    }, [selectedZoneIds, selectedGuildTagIds, selectedDays]);

    const datesInitialized = useRef(false);
    useEffect(() => {
        if (!datesInitialized.current) {
            datesInitialized.current = true;
            return;
        }
        reloadReports(buildFilters());
    }, [sinceDate, beforeDate]);

    const showSkeleton = isReloading || !reports;

    return (
        <Master title="Raid Reports">
            <SharedHeader title="Raid Reports" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Filter controls */}
                    <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
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
                        <FilterDropdown
                            label="Days"
                            options={DAYS}
                            selected={selectedDays}
                            onChange={setSelectedDays}
                            dusk="filter-day"
                        />
                        <DateFilterButton
                            label="After"
                            value={sinceDate}
                            onChange={setSinceDate}
                            dusk="filter-since-date"
                            min={earliestDate}
                        />
                        <DateFilterButton
                            label="Before"
                            value={beforeDate}
                            onChange={setBeforeDate}
                            dusk="filter-before-date"
                            min={earliestDate}
                        />
                    </div>

                    {/* Reports */}
                    {showSkeleton ? (
                        <ReportsSkeleton />
                    ) : (
                        <>
                            <ReportsTable reports={reports} />
                            <Pagination
                                links={reports.links}
                                meta={reports}
                                itemName="reports"
                            />
                        </>
                    )}
                </div>
            </div>
        </Master>
    );
}
