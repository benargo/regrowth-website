import { useState, useEffect, useRef } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import GuildRankLabel from "@/Components/GuildRankLabel";
import { Button } from "@headlessui/react";

function formatDuration(startTime, endTime) {
    const minutes = Math.floor((new Date(endTime) - new Date(startTime)) / 60000);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

function ViewOnWarcraftLogsLink({ code, children }) {
    return (
        <Tooltip text="View on Warcraft Logs" position="top">
            <Link
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
            </Link>
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
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Attendance</th>
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
                            <td className="px-4 py-3 text-sm text-gray-300">{character.playable_class?.name ?? "—"}</td>
                            <td className="px-4 py-3 text-sm text-gray-300">{character.playable_race?.name ?? "—"}</td>
                            <td className="px-4 py-3">
                                <Link
                                    href={route('raids.attendance.matrix', { character: character.id })}
                                    className="text-sm text-amber-400 hover:text-amber-300 hover:underline"
                                >
                                    View attendance
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function LinkReportsModal({ isOpen, onClose, currentReport, nearbyReports, onSubmit, isSubmitting }) {
    const alreadyLinkedCodes = new Set((currentReport.linked_reports ?? []).map((r) => r.code));
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
                data: { nearby_page: 1 },
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
            data: { nearby_page: page },
            preserveState: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const toggleCode = (code) => {
        if (code === currentReport.code || alreadyLinkedCodes.has(code)) {
            return;
        }
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(code)) {
                next.delete(code);
            } else {
                next.add(code);
            }
            return next;
        });
    };

    const newlySelectedCount = [...selected].filter((c) => !alreadyLinkedCodes.has(c)).length;

    const skeletonRows = Array.from({ length: 15 });

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
                        {nearbyReports.data.map((report) => {
                            const isCurrent = report.id === currentReport.id;
                            const isLinked = alreadyLinkedCodes.has(report.code);
                            const isChecked = isCurrent || isLinked || selected.has(report.code);
                            const isDisabled = isCurrent || isLinked;
                            const formattedDate = formatDate(report.start_time);

                            return (
                                <li
                                    key={report.id}
                                    onClick={() => toggleCode(report.code)}
                                    className={`flex cursor-pointer items-center gap-3 px-6 py-3 transition-colors ${isDisabled ? "cursor-default opacity-60" : "hover:bg-brown-800/50"}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        disabled={isDisabled}
                                        onChange={() => toggleCode(report.code)}
                                        onClick={(e) => e.stopPropagation()}
                                        className="h-4 w-4 rounded border-amber-600 bg-brown-800 text-amber-500 accent-amber-500"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium text-white">{report.title}</span>
                                            {isCurrent && (
                                                <span className="flex-shrink-0 rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                                    Current
                                                </span>
                                            )}
                                            {isLinked && (
                                                <span className="flex-shrink-0 rounded bg-green-600/20 px-1.5 py-0.5 text-xs text-green-400">
                                                    Linked
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            <span className="md:hidden">{formattedDate.short}</span>
                                            <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                            <span className="hidden lg:inline">{formattedDate.long}</span>
                                            {report.zone?.name && (
                                                <span className="ml-2">&mdash; {report.zone.name}</span>
                                            )}
                                        </p>
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
                    onClick={() => onSubmit([...selected])}
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
                    This will remove{" "}
                    <span className="font-medium text-white">{currentReport.title}</span> from all manually linked
                    reports. The following links will be severed:
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
                                        {report.zone?.name && <span className="ml-2">&mdash; {report.zone.name}</span>}
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

function LinkedReportsSection({ linkedReports, canManageLinks, onAddLink, onDelete }) {
    return (
        <div className="mt-8">
            <h2 className="mb-4 text-xl font-semibold text-white">Linked Reports</h2>

            {linkedReports && linkedReports.length > 0 && (
                <div className="divide-y divide-brown-700 rounded border border-amber-600/30">
                    {linkedReports.map((linked) => {
                        const formattedDate = formatDate(linked.start_time);
                        return (
                            <div key={linked.id} className="flex items-center justify-between px-4 py-3">
                                <div>
                                    <Link
                                        href={route("raids.reports.show", { report: linked.id })}
                                        className="font-medium text-amber-400 hover:text-amber-300 hover:underline"
                                    >
                                        {linked.title}
                                    </Link>
                                    <p className="flex items-center gap-2 mt-0.5 text-xs text-gray-500">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                        {linked.zone?.name && (
                                            <span className="ml-2 text-gray-500">{linked.zone.name}</span>
                                        )}
                                        {linked.pivot?.created_by && (
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
                                    {canManageLinks && (
                                        <Tooltip
                                            text={
                                                linked.pivot?.created_by
                                                    ? "Remove link"
                                                    : "Auto-linked — cannot be removed"
                                            }
                                            position="top"
                                        >
                                            <button
                                                onClick={() => linked.pivot?.created_by && onDelete(linked)}
                                                disabled={!linked.pivot?.created_by}
                                                className="rounded p-1.5 text-gray-500 transition-colors hover:bg-red-700/20 hover:text-red-400 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <Icon icon="unlink" style="solid" className="text-xs" />
                                            </button>
                                        </Tooltip>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {canManageLinks && (
                <button
                    onClick={onAddLink}
                    className="mt-4 inline-flex items-center gap-2 rounded border border-amber-600/50 px-4 py-2 text-sm text-gray-300 transition-colors hover:border-amber-600 hover:bg-amber-600/10 hover:text-white"
                >
                    <Icon icon="plus" style="solid" className="text-amber-500" />
                    Add Link
                </button>
            )}
        </div>
    );
}

export default function Show({ report, nearbyReports, impactedReports, canManageLinks }) {
    const data = report.data;
    const startDate = new Date(data.start_time);
    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
    const formattedDate = formatDate(data.start_time);
    const duration = formatDuration(data.start_time, data.end_time);

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isDeletingLink, setIsDeletingLink] = useState(false);

    const handleAddLink = () => {
        setIsModalOpen(true);
    };

    const handleDeleteLink = () => {
        setIsDeleteModalOpen(true);
    };

    const handleConfirmDelete = () => {
        setIsDeletingLink(true);
        router.patch(
            route("raids.reports.destroy-links", { report: data.id }),
            {},
            {
                onSuccess: () => setIsDeleteModalOpen(false),
                onFinish: () => setIsDeletingLink(false),
            },
        );
    };

    const handleSubmit = (codes) => {
        setIsSubmitting(true);
        router.post(
            route("raids.reports.store-links", { report: data.id }),
            { codes },
            {
                onSuccess: () => {
                    setIsModalOpen(false);
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <Master title={data.title}>
            <SharedHeader title={data.title} backgroundClass="bg-illidan" />

            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {/* Back link */}
                    <div className="mb-6">
                        <Link
                            href={route("raids.reports.index")}
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
                            <div className="flex-shrink-0">
                                <ViewOnWarcraftLogsLink code={data.code}>View on Warcraft Logs</ViewOnWarcraftLogsLink>
                            </div>
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
                        <CharactersTable characters={data.characters} />
                    </div>

                    {/* Linked reports */}
                    <LinkedReportsSection
                        linkedReports={data.linked_reports}
                        canManageLinks={canManageLinks}
                        onAddLink={handleAddLink}
                        onDelete={handleDeleteLink}
                    />

                    <LinkReportsModal
                        isOpen={isModalOpen}
                        onClose={() => setIsModalOpen(false)}
                        currentReport={data}
                        nearbyReports={nearbyReports}
                        onSubmit={handleSubmit}
                        isSubmitting={isSubmitting}
                    />

                    <DeleteLinkModal
                        isOpen={isDeleteModalOpen}
                        onClose={() => setIsDeleteModalOpen(false)}
                        currentReport={data}
                        impactedReports={impactedReports}
                        onConfirm={handleConfirmDelete}
                        isDeleting={isDeletingLink}
                    />
                </div>
            </div>
        </Master>
    );
}
