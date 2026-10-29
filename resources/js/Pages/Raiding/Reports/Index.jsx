import { useEffect, useRef, useState } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import DateFilterButton from "@/Components/DateFilterButton";
import Pagination from "@/Components/Pagination";
import Table, { Skeleton } from "@/Components/Raiding/Reports/Table";
import { decodeFilter, encodeFilter } from "@/Helpers/EncodeFilter";
import { Can } from "@/Components/Authorizable";
import FilterDropdown from "@/Components/FilterDropdown";
import EmptyState from "@/Components/EmptyState";
import SectionNav from "@/Components/Navigation/SectionNav";
import { useResetQueryOnGameVersionSwitch } from "@/Hooks/useGameVersionSwitch";

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

export default function Index({ reports, zones, guildTags, filters, earliestDate, hasReports }) {
    useResetQueryOnGameVersionSwitch();

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
        "filter[zone_ids]": encodeFilter(selectedZoneIds, zones),
        "filter[guild_tag_ids]": encodeFilter(selectedGuildTagIds, guildTags),
        "filter[days]": encodeFilter(selectedDays, DAYS),
        "filter[since_date]": sinceDate || undefined,
        "filter[before_date]": beforeDate || undefined,
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
            <SectionNav section="raiding" />
            <PageContainer>
                {/* Actions */}
                <Can permission="manage-reports">
                    <div className="mb-4 flex justify-end">
                        <Link
                            href={route("raiding.reports.create")}
                            className="inline-flex items-center gap-2 rounded-md border border-transparent bg-amber-700 px-4 py-2 text-sm font-semibold tracking-widest text-white uppercase transition hover:bg-amber-800 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:outline-hidden"
                        >
                            <Icon icon="plus" style="solid" className="h-4" />
                            Create a manual report
                        </Link>
                    </div>
                </Can>

                {!hasReports ? (
                    <EmptyState icon="scroll" message="Reports are not available for this game version." />
                ) : (
                    <>
                        {/* Filter controls */}
                        <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <FilterDropdown
                                label={{ singular: "Zone", plural: "Zones" }}
                                options={zones}
                                selected={selectedZoneIds}
                                onChange={setSelectedZoneIds}
                            />
                            <FilterDropdown
                                label={{ singular: "Tag", plural: "Tags" }}
                                options={guildTags}
                                selected={selectedGuildTagIds}
                                onChange={setSelectedGuildTagIds}
                            />
                            <FilterDropdown
                                label={{ singular: "Day", plural: "Days" }}
                                options={DAYS}
                                selected={selectedDays}
                                onChange={setSelectedDays}
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
                            <Skeleton />
                        ) : hasEmptyFilter ? (
                            <Table reports={{ data: [] }} />
                        ) : (
                            <>
                                <Table reports={reports} />
                                <Pagination links={reports.meta.links} meta={reports.meta} itemName="reports" />
                            </>
                        )}
                    </>
                )}
            </PageContainer>
        </Master>
    );
}
