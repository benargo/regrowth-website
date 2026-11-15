import { Link, router } from "@inertiajs/react";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import { FormRow, OptionSelect } from "@/Components/FormControls";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import GrmToolNav from "@/Components/GuildRosterManager/GrmToolNav";
import RosterFreshnessCard from "@/Components/GuildRosterManager/RosterFreshnessCard";
import {
    OUTCOME_LABELS,
    OUTCOME_TONES,
    REASON_LABELS,
    ROW_STATUS_LABELS,
    SOURCE_LABELS,
} from "@/Components/GuildRosterManager/syncLabels";
import PageContainer from "@/Components/PageContainer";
import Pagination from "@/Components/Pagination";
import SharedHeader from "@/Components/SharedHeader";
import StatTile from "@/Components/StatTile";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import cn from "@/Helpers/cn";
import { formatDateTime } from "@/Helpers/serverTime";
import Master from "@/Layouts/Master";

const COUNT_TILES = [
    { key: "processedCount", label: "Processed", marker: "bg-green-500" },
    { key: "skippedCount", label: "Skipped", marker: "bg-gray-400" },
    { key: "warningCount", label: "Warnings", marker: "bg-yellow-500" },
    { key: "errorCount", label: "Errors", marker: "bg-red-500" },
];

const RELOAD_OPTIONS = { preserveState: true, preserveScroll: true };

function NotAvailable() {
    return (
        <>
            <span aria-hidden="true">–</span>
            <span className="sr-only">Not available</span>
        </>
    );
}

function SectionHeading({ id, children }) {
    return (
        <h2 id={id} className="mb-4 text-xl font-bold text-white">
            {children}
        </h2>
    );
}

function RosterSkeleton() {
    return (
        <div aria-hidden="true" className="rounded border border-amber-600/40 p-4">
            <div className="bg-brown-800 h-5 w-1/2 rounded motion-safe:animate-pulse" />
            <div className="bg-brown-800 mt-4 h-4 w-1/3 rounded motion-safe:animate-pulse" />
            <div className="bg-brown-800 mt-2 h-4 w-3/4 rounded motion-safe:animate-pulse" />
        </div>
    );
}

function Rosters({ rosters }) {
    if (rosters === undefined) {
        return (
            <>
                <p className="sr-only" role="status">
                    Loading rosters…
                </p>
                <div className="grid gap-4 md:grid-cols-2" aria-busy="true">
                    <RosterSkeleton />
                    <RosterSkeleton />
                </div>
            </>
        );
    }

    if (rosters.length === 0) {
        return <p className="text-gray-400">No game version has a guild roster yet.</p>;
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {rosters.map((roster) => (
                <RosterFreshnessCard key={roster.gameVersion.slug} roster={roster} />
            ))}
        </div>
    );
}

function UploadsTable({ uploads, selectedUpload }) {
    if (uploads.length === 0) {
        return <EmptyState icon="file-upload" message="No uploads or syncs yet." />;
    }

    function selectUpload(id) {
        router.get(
            route("management.grm.sync"),
            { upload: id },
            { ...RELOAD_OPTIONS, only: ["rows", "selectedUpload", "selectedReason"] },
        );
    }

    return (
        <Table>
            <caption className="sr-only">Recent uploads and syncs. Choose one to see its rows.</caption>
            <TableHead>
                <tr>
                    <TableHeader scope="col" className="text-left">
                        Started
                    </TableHeader>
                    <TableHeader scope="col" className="text-left">
                        Roster
                    </TableHeader>
                    <TableHeader scope="col" className="text-left">
                        Source
                    </TableHeader>
                    <TableHeader scope="col" className="text-left">
                        Outcome
                    </TableHeader>
                    <TableHeader scope="col" className="text-right">
                        Processed
                    </TableHeader>
                    <TableHeader scope="col" className="text-right">
                        Skipped
                    </TableHeader>
                    <TableHeader scope="col" className="text-right">
                        Warnings
                    </TableHeader>
                    <TableHeader scope="col" className="text-right">
                        Errors
                    </TableHeader>
                </tr>
            </TableHead>
            <TableBody>
                {uploads.map((upload) => {
                    const isSelected = upload.id === selectedUpload;
                    const sentBy = [upload.uploader?.name, upload.device?.name].filter(Boolean).join(", ");

                    return (
                        <TableRow
                            key={upload.id}
                            onClick={() => selectUpload(upload.id)}
                            className={cn("cursor-pointer", isSelected && "bg-brown-800")}
                        >
                            <TableCell className="whitespace-nowrap">
                                <Link
                                    href={route("management.grm.sync", { upload: upload.id })}
                                    only={["rows", "selectedUpload", "selectedReason"]}
                                    preserveState
                                    preserveScroll
                                    aria-current={isSelected ? "true" : undefined}
                                    onClick={(event) => event.stopPropagation()}
                                    className={cn(
                                        "rounded hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                                        isSelected ? "font-semibold text-white" : "text-amber-200",
                                    )}
                                >
                                    {formatDateTime(upload.startedAt, null)}
                                </Link>
                            </TableCell>
                            <TableCell className="text-gray-300">
                                {upload.gameVersion ? (
                                    <span className="flex items-center gap-2">
                                        <GameVersionIcon gameVersion={upload.gameVersion} />
                                        {upload.gameVersion.title}
                                    </span>
                                ) : (
                                    <NotAvailable />
                                )}
                            </TableCell>
                            <TableCell className="text-gray-300">
                                {SOURCE_LABELS[upload.source]}
                                {sentBy && <span className="block text-sm text-gray-400">{sentBy}</span>}
                            </TableCell>
                            <TableCell className={OUTCOME_TONES[upload.outcome]}>
                                {OUTCOME_LABELS[upload.outcome]}
                            </TableCell>
                            <TableCell className="text-right text-gray-300 tabular-nums">
                                {upload.processedCount ?? <NotAvailable />}
                            </TableCell>
                            <TableCell className="text-right text-gray-300 tabular-nums">
                                {upload.skippedCount ?? <NotAvailable />}
                            </TableCell>
                            <TableCell className="text-right text-gray-300 tabular-nums">
                                {upload.warningCount ?? <NotAvailable />}
                            </TableCell>
                            <TableCell className="text-right text-gray-300 tabular-nums">
                                {upload.errorCount ?? <NotAvailable />}
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}

function RowOutcome({ row }) {
    const isProblem = !row.meta.is_expected && (row.status === "failed" || row.status === "warning");

    return (
        <TableRow className={cn(isProblem && "bg-red-950/40")}>
            <TableCell className={cn("border-l-4", isProblem ? "border-red-500" : "border-transparent")}>
                {row.characterId && row.characterSlug ? (
                    <Link
                        href={route("characters.show", { character: row.characterId, slug: row.characterSlug })}
                        className="rounded text-amber-200 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                    >
                        {row.name}
                    </Link>
                ) : (
                    <span className="text-gray-200">{row.name}</span>
                )}
            </TableCell>
            <TableCell className={isProblem ? "font-semibold text-red-300" : "text-gray-300"}>
                <span className="flex items-center gap-2">
                    <Icon
                        icon="exclamation-circle"
                        style="solid"
                        className={cn("text-red-400", !isProblem && "hidden")}
                        aria-hidden="true"
                    />
                    {ROW_STATUS_LABELS[row.status]}
                </span>
            </TableCell>
            <TableCell className={row.meta.is_expected ? "text-gray-400" : "text-gray-200"}>
                <span className="flex items-center gap-2">
                    <Icon
                        icon="info-circle"
                        style="solid"
                        className={cn("text-blue-300", !row.meta.is_expected && "hidden")}
                        aria-hidden="true"
                    />
                    {REASON_LABELS[row.reason]}
                    {row.meta.is_expected && <span className="text-xs text-gray-400">(expected)</span>}
                </span>
            </TableCell>
            <TableCell className="text-sm text-gray-400">{row.message}</TableCell>
        </TableRow>
    );
}

/**
 * Summarise the selected upload's rows for screen readers, e.g. "12 rows with the reason Created for Classic,
 * 14 Nov 2026, 21:30". Empty when no upload is selected.
 */
function describeRows(rows, selectedReason, upload) {
    if (!rows) {
        return "";
    }

    return [
        rows.meta.total === 1 ? "1 row" : `${rows.meta.total} rows`,
        selectedReason ? ` with the reason ${REASON_LABELS[selectedReason]}` : "",
        upload ? ` for ${upload.gameVersion?.title}, ${formatDateTime(upload.startedAt, null)}` : "",
    ].join("");
}

function RowOutcomes({ rows, reasons, selectedUpload, selectedReason, upload }) {
    function filterByReason(reason) {
        router.get(
            route("management.grm.sync"),
            { upload: selectedUpload, reason: reason || undefined },
            { ...RELOAD_OPTIONS, only: ["rows", "selectedReason"] },
        );
    }

    return (
        <>
            {upload && (
                <dl className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                    {COUNT_TILES.map((tile) => (
                        <StatTile
                            key={tile.key}
                            label={tile.label}
                            value={upload[tile.key] ?? <NotAvailable />}
                            markerClassName={tile.marker}
                        />
                    ))}
                </dl>
            )}

            <FormRow htmlFor="reason" label="Reason" className="mb-4 max-w-xs">
                <OptionSelect
                    name="reason"
                    value={selectedReason ?? ""}
                    onChange={filterByReason}
                    placeholder="All reasons"
                    groups={[
                        {
                            label: "Needs attention or changed",
                            options: reasons
                                .filter((reason) => !reason.isExpected)
                                .map((reason) => ({ value: reason.value, label: REASON_LABELS[reason.value] })),
                        },
                        {
                            label: "Expected",
                            options: reasons
                                .filter((reason) => reason.isExpected)
                                .map((reason) => ({ value: reason.value, label: REASON_LABELS[reason.value] })),
                        },
                    ].filter((group) => group.options.length > 0)}
                />
            </FormRow>

            {rows.data.length === 0 ? (
                <EmptyState icon="clipboard-list-check" message="No rows match." />
            ) : (
                <>
                    <Table>
                        <caption className="sr-only">Row outcomes, failures and warnings first</caption>
                        <TableHead>
                            <tr>
                                <TableHeader scope="col" className="text-left">
                                    Name
                                </TableHeader>
                                <TableHeader scope="col" className="text-left">
                                    Status
                                </TableHeader>
                                <TableHeader scope="col" className="text-left">
                                    Reason
                                </TableHeader>
                                <TableHeader scope="col" className="text-left">
                                    Details
                                </TableHeader>
                            </tr>
                        </TableHead>
                        <TableBody>
                            {rows.data.map((row) => (
                                <RowOutcome key={row.id} row={row} />
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination links={rows.links} meta={rows.meta} itemName="rows" />
                </>
            )}
        </>
    );
}

export default function Sync({ rosters, uploads: { data: uploads }, selectedUpload, selectedReason, reasons, rows }) {
    const upload = uploads.find((candidate) => candidate.id === selectedUpload) ?? null;

    return (
        <Master title="GRM Sync Dashboard">
            <SharedHeader title="GRM Sync Dashboard" backgroundClass="bg-officer-meeting" />
            <GrmToolNav current="management.grm.sync" />
            <PageContainer>
                <div className="flex flex-col gap-10">
                    <section aria-labelledby="rosters-heading">
                        <SectionHeading id="rosters-heading">Rosters</SectionHeading>
                        <Rosters rosters={rosters} />
                    </section>

                    <section aria-labelledby="uploads-heading">
                        <SectionHeading id="uploads-heading">Recent uploads and syncs</SectionHeading>
                        <UploadsTable uploads={uploads} selectedUpload={selectedUpload} />
                    </section>

                    <p className="sr-only" role="status">
                        {selectedUpload ? describeRows(rows, selectedReason, upload) : ""}
                    </p>

                    {selectedUpload && (
                        <section aria-labelledby="rows-heading">
                            <SectionHeading id="rows-heading">
                                Rows
                                {upload && <span className="sr-only">: </span>}
                                {upload && (
                                    <span className="ml-2 text-base font-normal text-gray-400">
                                        {upload.gameVersion?.title}, {formatDateTime(upload.startedAt, null)}
                                    </span>
                                )}
                            </SectionHeading>
                            <RowOutcomes
                                rows={rows}
                                reasons={reasons}
                                selectedUpload={selectedUpload}
                                selectedReason={selectedReason}
                                upload={upload}
                            />
                        </section>
                    )}
                </div>
            </PageContainer>
        </Master>
    );
}
