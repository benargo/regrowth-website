import { useState, useEffect, useRef } from "react";
import { Link, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import getDayDifference from "@/Helpers/GetDayDifference";
import usePermission from "@/Hooks/Permissions";

function ClusterReportRow({ report, dayDiff }) {
    const formattedDate = formatDate(report.start_time);
    return (
        <div>
            <p className="truncate text-sm font-medium text-white">{report.title}</p>
            <div className="flex flex-col gap-2 md:flex-row">
                <p className="mt-0.5 text-xs text-gray-500">{report.zone?.name}</p>
                <p className="mt-0.5 text-xs text-gray-500 md:border-l md:border-gray-700 md:pl-2">
                    <span className="md:hidden">{formattedDate.short}</span>
                    <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                    <span className="hidden lg:inline">{formattedDate.long}</span>
                    {dayDiff && <span>&nbsp;&ndash;&nbsp;{dayDiff}</span>}
                </p>
            </div>
        </div>
    );
}

function LinkReportsModal({
    isOpen,
    onClose,
    alreadyLinkedIds,
    currentId,
    nearbyReports,
    onSubmit,
    isSubmitting,
    isCreateMode,
    referenceDate,
}) {
    const [selected, setSelected] = useState(new Set());
    const [isLoading, setIsLoading] = useState(false);
    const initialLoadDone = useRef(false);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        if (!initialLoadDone.current) {
            initialLoadDone.current = true;
            setIsLoading(true);
            router.reload({
                only: ["nearbyReports"],
                data: { ["nearby"]: 1 },
                preserveState: true,
                onFinish: () => setIsLoading(false),
            });
        }
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) {
            initialLoadDone.current = false;
            setSelected(new Set());
        }
    }, [isOpen]);

    const handlePageChange = (page) => {
        setIsLoading(true);
        router.reload({
            only: ["nearbyReports"],
            data: { ["nearby"]: page },
            preserveState: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const isClusterCurrent = (cluster) => !isCreateMode && cluster.reports.some((r) => r.id === currentId);

    const isClusterLinked = (cluster) => cluster.reports.some((r) => alreadyLinkedIds.has(r.id));

    const toggleCluster = (cluster) => {
        if (isClusterCurrent(cluster) || isClusterLinked(cluster)) {
            return;
        }
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(cluster.id)) {
                next.delete(cluster.id);
            } else {
                next.add(cluster.id);
            }
            return next;
        });
    };

    const newlySelectedCount = [...selected].reduce((n, clusterId) => {
        const cluster = nearbyReports?.data?.find((c) => c.id === clusterId);
        return n + (cluster?.reports.filter((r) => !alreadyLinkedIds.has(r.id)).length ?? 0);
    }, 0);

    const handleSubmit = () => {
        const allIds = [...selected].flatMap((clusterId) => {
            const cluster = nearbyReports?.data?.find((c) => c.id === clusterId);
            return cluster ? cluster.reports.map((r) => r.id) : [];
        });
        onSubmit(allIds);
    };

    const skeletonRows = Array.from({ length: 5 });
    const meta = nearbyReports?.meta;

    return (
        <Modal show={isOpen} maxWidth="xl" onClose={onClose}>
            <div className="flex items-center justify-between border-b border-amber-600/30 px-6 py-4">
                <h2 className="text-lg font-semibold text-white">Link Reports</h2>
                <button onClick={onClose} className="text-gray-400 transition-colors hover:text-white">
                    <Icon icon="times" style="solid" />
                </button>
            </div>

            <div className="max-h-[60vh] overflow-y-auto">
                {isLoading ? (
                    <ul className="divide-y divide-brown-700">
                        {skeletonRows.map((_, i) => (
                            <li key={i} className="flex animate-pulse items-center gap-3 px-6 py-3">
                                <div className="h-4 w-4 rounded bg-brown-700" />
                                <div className="flex-1 space-y-1.5">
                                    <div className="h-3.5 w-48 rounded bg-brown-700" />
                                    <div className="h-3 w-32 rounded bg-brown-700" />
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : !nearbyReports || nearbyReports.data.length === 0 ? (
                    <div className="py-12 text-center text-gray-400">
                        <p>No other reports found.</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-brown-700">
                        {nearbyReports.data.map((cluster) => {
                            const linked = isClusterLinked(cluster);
                            const current = isClusterCurrent(cluster);
                            const isChecked = current || linked || selected.has(cluster.id);
                            const isDisabled = current || linked;
                            const isSingle = cluster.reports.length === 1;
                            const clusterDayDiff = getDayDifference(referenceDate, cluster.reports[0]?.start_time);

                            return (
                                <li
                                    key={cluster.id}
                                    onClick={() => toggleCluster(cluster)}
                                    className={`flex gap-3 px-6 py-3 transition-colors ${isDisabled ? "cursor-default opacity-60" : "cursor-pointer hover:bg-brown-800/50"}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        disabled={isDisabled}
                                        onChange={() => toggleCluster(cluster)}
                                        onClick={(e) => e.stopPropagation()}
                                        className="mt-1 h-4 w-4 flex-shrink-0 rounded border-amber-600 bg-brown-800 text-amber-500 accent-amber-500"
                                    />
                                    <div className="min-w-0 flex-1">
                                        {!isSingle && (
                                            <div className="mb-2 flex items-center gap-2">
                                                <span className="text-xs text-gray-500">
                                                    {cluster.reports.length} reports
                                                </span>
                                                {current && (
                                                    <span className="flex-shrink-0 rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                                        Current
                                                    </span>
                                                )}
                                                {linked && (
                                                    <span className="flex-shrink-0 rounded bg-green-600/20 px-1.5 py-0.5 text-xs text-green-400">
                                                        Linked
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                        <div className={!isSingle ? "space-y-2 border-l-2 border-brown-700 pl-3" : ""}>
                                            {isSingle ? (
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium text-white">
                                                            {cluster.reports[0].title}
                                                        </span>
                                                        {current && (
                                                            <span className="flex-shrink-0 rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                                                Current
                                                            </span>
                                                        )}
                                                        {linked && (
                                                            <span className="flex-shrink-0 rounded bg-green-600/20 px-1.5 py-0.5 text-xs text-green-400">
                                                                Linked
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-gray-500">
                                                        {(() => {
                                                            const d = formatDate(cluster.reports[0].start_time);
                                                            return (
                                                                <div className="flex flex-col gap-2 md:flex-row">
                                                                    <p className="mt-0.5 text-xs text-gray-500">
                                                                        {cluster.reports[0].zone.name}
                                                                    </p>
                                                                    <p className="mt-0.5 text-xs text-gray-500 md:border-l md:border-gray-700 md:pl-2">
                                                                        <span className="md:hidden">{d.short}</span>
                                                                        <span className="hidden md:inline lg:hidden">
                                                                            {d.medium}
                                                                        </span>
                                                                        <span className="hidden lg:inline">
                                                                            {d.long}
                                                                        </span>
                                                                        {clusterDayDiff && (
                                                                            <span>
                                                                                &nbsp;&ndash;&nbsp;{clusterDayDiff}
                                                                            </span>
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            );
                                                        })()}
                                                    </div>
                                                </div>
                                            ) : (
                                                cluster.reports.map((report) => (
                                                    <ClusterReportRow
                                                        key={report.id}
                                                        report={report}
                                                        dayDiff={getDayDifference(referenceDate, report.start_time)}
                                                    />
                                                ))
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="flex items-center justify-between border-t border-amber-600/30 px-6 py-4">
                <div className="flex items-center gap-3">
                    {meta && meta.last_page > 1 && (
                        <>
                            <button
                                onClick={() => handlePageChange(meta.current_page - 1)}
                                disabled={meta.current_page <= 1 || isLoading}
                                className="rounded border border-amber-600/30 px-3 py-1.5 text-sm text-gray-300 transition-colors hover:bg-amber-600/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <Icon icon="chevron-left" style="solid" />
                            </button>
                            <span className="text-sm text-gray-400">
                                Page {meta.current_page} of {meta.last_page}
                            </span>
                            <button
                                onClick={() => handlePageChange(meta.current_page + 1)}
                                disabled={meta.current_page >= meta.last_page || isLoading}
                                className="rounded border border-amber-600/30 px-3 py-1.5 text-sm text-gray-300 transition-colors hover:bg-amber-600/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <Icon icon="chevron-right" style="solid" />
                            </button>
                        </>
                    )}
                </div>
                <button
                    onClick={handleSubmit}
                    disabled={newlySelectedCount === 0 || isSubmitting}
                    className="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {isSubmitting
                        ? "Linking..."
                        : newlySelectedCount === 0
                          ? "Select reports to link"
                          : `Link ${newlySelectedCount} ${newlySelectedCount === 1 ? "report" : "reports"}`}
                </button>
            </div>
        </Modal>
    );
}

function DeleteLinkModal({ isOpen, onClose, currentReport, impactedReports, onConfirm, isDeleting }) {
    const [isLoading, setIsLoading] = useState(false);
    const initialLoadDone = useRef(false);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        if (!initialLoadDone.current) {
            initialLoadDone.current = true;
            setIsLoading(true);
            router.reload({
                only: ["impactedReports"],
                preserveState: true,
                onFinish: () => setIsLoading(false),
            });
        }
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) {
            initialLoadDone.current = false;
        }
    }, [isOpen]);

    const skeletonRows = Array.from({ length: 5 });

    return (
        <Modal show={isOpen} maxWidth="md" onClose={onClose}>
            <div className="flex items-center justify-between border-b border-amber-600/30 px-6 py-4">
                <h2 className="text-lg font-semibold text-white">Remove Links</h2>
                <button onClick={onClose} className="text-gray-400 transition-colors hover:text-white">
                    <Icon icon="times" style="solid" />
                </button>
            </div>

            <div className="px-6 py-4">
                <p className="mb-4 text-sm text-gray-400">
                    This will remove <span className="font-medium text-white">{currentReport.title}</span> from all
                    manually linked reports. The following links will be severed:
                </p>
                {isLoading ? (
                    <ul className="divide-y divide-brown-700 rounded border border-amber-600/30">
                        {skeletonRows.map((_, i) => (
                            <li key={i} className="flex animate-pulse items-center gap-3 px-4 py-3">
                                <div className="flex-1 space-y-1.5">
                                    <div className="h-3.5 w-48 rounded bg-brown-700" />
                                    <div className="h-3 w-32 rounded bg-brown-700" />
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : !impactedReports?.data || impactedReports.data.length === 0 ? (
                    <p className="text-sm text-gray-500">No manually linked reports found.</p>
                ) : (
                    <ul className="divide-y divide-brown-700 rounded border border-amber-600/30">
                        {impactedReports.data.map((report) => {
                            const formattedDate = formatDate(report.start_time);
                            return (
                                <li key={report.id} className="px-4 py-3">
                                    <span className="text-sm font-medium text-white">{report.title}</span>
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                        {report.zone?.name && <span className="ml-2">&ndash; {report.zone.name}</span>}
                                    </p>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <div className="flex justify-end gap-3 border-t border-amber-600/30 px-6 py-4">
                <button
                    onClick={onClose}
                    disabled={isDeleting}
                    className="rounded border border-amber-600/30 px-4 py-2 text-sm text-gray-300 transition-colors hover:bg-amber-600/10 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Cancel
                </button>
                <button
                    onClick={onConfirm}
                    disabled={isDeleting || isLoading}
                    className="rounded bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {isDeleting ? "Removing..." : "Confirm"}
                </button>
            </div>
        </Modal>
    );
}

export default function LinkedRaidReports({ currentReport, nearbyReports, impactedReports, onChange, referenceDate }) {
    const canManageReports = usePermission("manage-reports");
    const isCreateMode = currentReport === null;

    const [localLinkedReports, setLocalLinkedReports] = useState([]);

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isDeletingLink, setIsDeletingLink] = useState(false);

    const linkedReports = isCreateMode ? localLinkedReports : (currentReport.linked_reports ?? []);

    const alreadyLinkedIds = new Set(linkedReports.map((r) => r.id));

    const handleAddLink = () => setIsModalOpen(true);

    const handleShowModeSubmit = (identifiers) => {
        setIsSubmitting(true);
        router.patch(
            route("raiding.reports.update", { report: currentReport.id }),
            { links: { action: "create", link_ids: identifiers } },
            {
                preserveScroll: true,
                onSuccess: () => setIsModalOpen(false),
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const handleCreateModeSubmit = (ids) => {
        const allReports = (nearbyReports?.data ?? []).flatMap((c) => c.reports);
        const newReports = allReports.filter((r) => ids.includes(r.id) && !alreadyLinkedIds.has(r.id));
        const updated = [...localLinkedReports, ...newReports];
        setLocalLinkedReports(updated);
        setIsModalOpen(false);
        onChange?.(updated.map((r) => r.id));
    };

    const handleCreateModeRemove = (reportToRemove) => {
        const updated = localLinkedReports.filter((r) => r.id !== reportToRemove.id);
        setLocalLinkedReports(updated);
        onChange?.(updated.map((r) => r.id));
    };

    const handleShowModeDelete = () => setIsDeleteModalOpen(true);

    const handleConfirmDelete = () => {
        setIsDeletingLink(true);
        router.patch(
            route("raiding.reports.update", { report: currentReport.id }),
            { links: { action: "delete", link_ids: [] } },
            {
                preserveScroll: true,
                onSuccess: () => setIsDeleteModalOpen(false),
                onFinish: () => setIsDeletingLink(false),
            },
        );
    };

    return (
        <div className="mt-6">
            <h2 className="mb-3 text-xl font-semibold text-white">Linked Reports</h2>

            {linkedReports.length === 0 && (
                <div className="flex flex-row gap-2 text-gray-400">
                    <Icon icon="scroll" style="solid" className="text-xl" />
                    <p>No linked reports.</p>
                </div>
            )}

            {linkedReports.length > 0 && (
                <div className="divide-y divide-brown-700 rounded border border-amber-600/30">
                    {linkedReports.map((linked) => {
                        const formattedDate = formatDate(linked.start_time);
                        const isManualLink = linked.pivot?.created_by;
                        const dayDiff = getDayDifference(referenceDate, linked.start_time);

                        return (
                            <div key={linked.id} className="flex items-center justify-between px-4 py-3">
                                <div>
                                    {isCreateMode ? (
                                        <span className="font-medium text-amber-400">{linked.title}</span>
                                    ) : (
                                        <Link
                                            href={route("raiding.reports.show", { report: linked.id })}
                                            className="font-medium text-amber-400 hover:text-amber-300 hover:underline"
                                        >
                                            {linked.title}
                                        </Link>
                                    )}
                                    <p className="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                        {linked.zone?.name && (
                                            <span className="ml-2 text-gray-500">{linked.zone.name}</span>
                                        )}
                                        {dayDiff && <span className="ml-2 text-gray-500">{dayDiff}</span>}
                                        {!isCreateMode && isManualLink && (
                                            <span className="ml-2 text-gray-500">
                                                Linked by {linked.pivot.created_by.display_name} on{" "}
                                                {new Date(linked.pivot.created_at).toLocaleString("en-GB", {
                                                    day: "numeric",
                                                    month: "long",
                                                    year: "numeric",
                                                    hour: "2-digit",
                                                    minute: "2-digit",
                                                })}
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="ml-2">
                                    {isCreateMode ? (
                                        <button
                                            type="button"
                                            onClick={() => handleCreateModeRemove(linked)}
                                            className="rounded p-1.5 text-gray-500 transition-colors hover:bg-red-700/20 hover:text-red-400"
                                        >
                                            <Icon icon="unlink" style="solid" className="text-xs" />
                                        </button>
                                    ) : (
                                        canManageReports && (
                                            <Tooltip
                                                text={isManualLink ? "Remove link" : "Auto-linked – cannot be removed"}
                                                position="left"
                                            >
                                                <button
                                                    onClick={() => isManualLink && handleShowModeDelete()}
                                                    disabled={!isManualLink}
                                                    className="rounded px-3 py-1.5 text-gray-500 transition-colors hover:bg-red-700/20 hover:text-red-400 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    <Icon icon="unlink" style="solid" className="text-xs" />
                                                </button>
                                            </Tooltip>
                                        )
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {canManageReports && (
                <button
                    type="button"
                    onClick={handleAddLink}
                    className="mt-4 inline-flex items-center gap-2 rounded border border-amber-600/50 px-4 py-2 text-sm text-gray-300 transition-colors hover:border-amber-600 hover:bg-amber-600/10 hover:text-white"
                >
                    <Icon icon="plus" style="solid" className="text-amber-500" />
                    Add Link
                </button>
            )}

            <LinkReportsModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                alreadyLinkedIds={alreadyLinkedIds}
                currentId={currentReport?.id ?? null}
                nearbyReports={nearbyReports}
                onSubmit={isCreateMode ? handleCreateModeSubmit : handleShowModeSubmit}
                isSubmitting={isSubmitting}
                isCreateMode={isCreateMode}
                referenceDate={referenceDate}
            />

            {!isCreateMode && (
                <DeleteLinkModal
                    isOpen={isDeleteModalOpen}
                    onClose={() => setIsDeleteModalOpen(false)}
                    currentReport={currentReport}
                    impactedReports={impactedReports}
                    onConfirm={handleConfirmDelete}
                    isDeleting={isDeletingLink}
                />
            )}
        </div>
    );
}
