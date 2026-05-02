import { useEffect, useRef, useState } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import DateFilterButton from "@/Components/DateFilterButton";
import Pagination from "@/Components/Pagination";
import formatDate from "@/Helpers/FormatDate";
import { decodeFilter, encodeFilter } from "@/Helpers/EncodeFilter";
import usePermission from "@/Hooks/Permissions";

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
                        {["w-32", "w-48", "w-24", "w-24", "w-16", "w-8"].map((w, i) => (
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
                            <td className="px-4 py-3 text-center">
                                <div className="mx-auto h-4 w-4 rounded bg-brown-700" />
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
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Tag</th>
                        <th className="px-4 py-3 text-right text-sm font-semibold text-amber-500">Duration</th>
                        <th className="px-4 py-3 text-center text-sm font-semibold text-amber-500">Linked</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {reports.data.map((report) => {
                        const startDate = new Date(report.start_time);
                        const endDate = new Date(report.end_time);
                        const durationMinutes = Math.floor((endDate - startDate) / 60000);
                        const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                        const formattedDate = formatDate(report.start_time);

                        return (
                            <tr key={report.id} className="transition-colors hover:bg-brown-800/50">
                                <td className="whitespace-nowrap px-4 py-3">
                                    <p className="text-xs text-gray-500">{dayOfWeek}</p>
                                    <p className="text-sm text-gray-300">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Link
                                        href={route("raiding.reports.show", report.id)}
                                        target="_blank"
                                        className="font-medium text-amber-400 hover:text-amber-300 hover:underline"
                                    >
                                        {report.title}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-300">{report.zone?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-sm text-gray-300">{report.guild_tag?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-right text-sm text-gray-300">
                                    {formatDuration(durationMinutes)}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    {report.linked_reports_count > 0 ? (
                                        <Tooltip text={`${report.linked_reports_count}`} position="right">
                                            <Icon icon="link" style="solid" className="text-amber-500" />
                                        </Tooltip>
                                    ) : (
                                        <Icon icon="link" style="solid" className="text-gray-600" />
                                    )}
                                </td>
                            </tr>
                        );
                    })}
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
        decodeFilter(
            filters.zone_ids,
            zones.map((z) => z.id),
        ),
    );
    const [selectedGuildTagIds, setSelectedGuildTagIds] = useState(() =>
        decodeFilter(
            filters.guild_tag_ids,
            guildTags.map((g) => g.id),
        ),
    );
    const [selectedDays, setSelectedDays] = useState(() =>
        decodeFilter(
            filters.days,
            DAYS.map((d) => d.id),
        ),
    );
    const [sinceDate, setSinceDate] = useState(filters.since_date ?? "");
    const [beforeDate, setBeforeDate] = useState(filters.before_date ?? "");

    const [isReloading, setIsReloading] = useState(false);

    const hasEmptyFilter =
        selectedZoneIds.length === 0 || selectedGuildTagIds.length === 0 || selectedDays.length === 0;

    const buildFilters = () => ({
        zone_ids: encodeFilter(selectedZoneIds, zones),
        guild_tag_ids: encodeFilter(selectedGuildTagIds, guildTags),
        days: encodeFilter(selectedDays, DAYS),
        since_date: sinceDate || undefined,
        before_date: beforeDate || undefined,
    });

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
        if (hasEmptyFilter) return;
        reloadReports(buildFilters());
    }, [selectedZoneIds, selectedGuildTagIds, selectedDays]);

    const datesInitialized = useRef(false);
    useEffect(() => {
        if (!datesInitialized.current) {
            datesInitialized.current = true;
            return;
        }
        if (hasEmptyFilter) return;
        reloadReports(buildFilters());
    }, [sinceDate, beforeDate]);

    const showSkeleton = !hasEmptyFilter && (isReloading || !reports);

    return (
        <Master title="Raid Reports">
            <SharedHeader title="Raid Reports" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Actions */}
                    {usePermission("manage-reports") && (
                        <div className="mb-4 flex justify-end">
                            <Link
                                href={route("raiding.reports.create")}
                                className="inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800"
                            >
                                <Icon icon="plus" style="solid" className="mr-1.5 h-4" />
                                Create a manual report
                            </Link>
                        </div>
                    )}

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
                            label="Tags"
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
                            min={earliestDate}
                            helpText="Leave blank to show all available dates."
                        />
                        <DateFilterButton
                            label="Before"
                            value={beforeDate}
                            onChange={setBeforeDate}
                            min={earliestDate}
                            helpText="Leave blank to show all available dates."
                        />
                    </div>

                    {/* Reports */}
                    {showSkeleton ? (
                        <ReportsSkeleton />
                    ) : hasEmptyFilter ? (
                        <ReportsTable reports={{ data: [] }} />
                    ) : (
                        <>
                            <ReportsTable reports={reports} />
                            <Pagination links={reports.meta.links} meta={reports.meta} itemName="reports" />
                        </>
                    )}
                </div>
            </div>
        </Master>
    );
}
