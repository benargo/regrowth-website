import { Fragment } from "react";
import { Deferred, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ToolNav from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import useLocalStorage from "@/Hooks/useLocalStorage";
import useLootPrioritiesChannel from "@/Hooks/useLootPrioritiesChannel";

function PriorityIcon({ icon, title, size = "h-5 w-5" }) {
    if (!icon) {
        return <span className={`${size} bg-brown-700 shrink-0 rounded`} />;
    }

    return <img src={icon} alt={title} className={`${size} shrink-0 rounded`} />;
}

function heatStyle(value, max) {
    if (!value || !max) {
        return undefined;
    }

    const opacity = Math.min(0.6, (value / max) * 0.6);

    return { backgroundColor: `rgba(217, 119, 6, ${opacity})` };
}

function RowCells({ row, phases, columnMax }) {
    return (
        <>
            {phases.map((phase) => {
                const value = row.counts[phase.id] ?? 0;

                return (
                    <td
                        key={phase.id}
                        className="px-3 py-2 text-center text-sm text-gray-200 tabular-nums"
                        style={heatStyle(value, columnMax[phase.id])}
                    >
                        {value}
                    </td>
                );
            })}
        </>
    );
}

function DesktopTable({ phases, rows, columnMax, phaseTotals }) {
    return (
        <div className="hidden overflow-x-auto rounded border border-amber-600/30 md:block">
            <table className="w-full min-w-max border-collapse">
                <thead className="bg-brown-900 sticky top-0 border-b border-amber-600/30">
                    <tr>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Priority</th>
                        {phases.map((phase) => (
                            <th key={phase.id} className="px-3 py-3 text-center text-sm font-semibold text-amber-500">
                                {phase.name}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-brown-700 divide-y">
                    {rows.map((row) =>
                        row.kind === "class" ? (
                            <Fragment key={`class-${row.id}`}>
                                <tr
                                    key={`class-${row.id}`}
                                    className={`border-l-4 border-playable-class-${row.slug} bg-playable-class-${row.slug}/10`}
                                >
                                    <td className="px-4 py-2">
                                        <div className="flex items-center gap-2">
                                            <PriorityIcon icon={row.icon} title={row.title} size="h-6 w-6" />
                                            <span className="text-sm font-semibold text-white">{row.title}</span>
                                        </div>
                                    </td>
                                    <RowCells row={row} phases={phases} columnMax={columnMax} />
                                </tr>
                                {row.children.map((child) => (
                                    <tr key={`priority-${child.id}`} className="hover:bg-brown-800/50">
                                        <td className="py-2 pr-4 pl-10">
                                            <div className="flex items-center gap-2">
                                                <PriorityIcon icon={child.icon} title={child.title} />
                                                <span className="text-sm text-gray-300">{child.title}</span>
                                            </div>
                                        </td>
                                        <RowCells row={child} phases={phases} columnMax={columnMax} />
                                    </tr>
                                ))}
                            </Fragment>
                        ) : (
                            <tr key={`priority-${row.id}`} className="hover:bg-brown-800/50">
                                <td className="px-4 py-2">
                                    <div className="flex items-center gap-2">
                                        <PriorityIcon icon={row.icon} title={row.title} />
                                        <span className="text-sm font-medium text-white">{row.title}</span>
                                    </div>
                                </td>
                                <RowCells row={row} phases={phases} columnMax={columnMax} />
                            </tr>
                        ),
                    )}
                </tbody>
                <tfoot className="bg-brown-900/60 border-t border-amber-600/30">
                    <tr>
                        <td className="px-4 py-3 text-sm font-semibold text-amber-500">Total</td>
                        {phases.map((phase) => (
                            <td
                                key={phase.id}
                                className="px-3 py-3 text-center text-sm font-semibold text-amber-400 tabular-nums"
                            >
                                {phaseTotals[phase.id] ?? 0}
                            </td>
                        ))}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function MobileTable({ phases, rows, selectedPhaseId, onSelectPhase, phaseTotals }) {
    const phase = phases.find((p) => p.id === selectedPhaseId) ?? phases[0];

    return (
        <div className="flex flex-col gap-4 md:hidden">
            <div className="flex overflow-x-auto rounded border border-amber-600/30">
                {phases.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        onClick={() => onSelectPhase(p.id)}
                        className={`flex-1 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
                            p.id === phase?.id
                                ? "bg-amber-600/20 text-amber-400"
                                : "hover:bg-brown-800/50 text-gray-400"
                        }`}
                    >
                        {p.name}
                    </button>
                ))}
            </div>

            {phase && (
                <div className="divide-brown-700 divide-y rounded border border-amber-600/30">
                    {rows.map((row) =>
                        row.kind === "class" ? (
                            <div key={`class-${row.id}`}>
                                <div
                                    className={`flex items-center justify-between border-l-4 border-playable-class-${row.slug} bg-playable-class-${row.slug}/10 px-4 py-2`}
                                >
                                    <div className="flex items-center gap-2">
                                        <PriorityIcon icon={row.icon} title={row.title} size="h-6 w-6" />
                                        <span className="text-sm font-semibold text-white">{row.title}</span>
                                    </div>
                                    <span className="text-sm font-semibold text-amber-400 tabular-nums">
                                        {row.counts[phase.id] ?? 0}
                                    </span>
                                </div>
                                {row.children.map((child) => (
                                    <div
                                        key={`priority-${child.id}`}
                                        className="flex items-center justify-between py-2 pr-4 pl-10"
                                    >
                                        <div className="flex items-center gap-2">
                                            <PriorityIcon icon={child.icon} title={child.title} />
                                            <span className="text-sm text-gray-300">{child.title}</span>
                                        </div>
                                        <span className="text-sm text-gray-200 tabular-nums">
                                            {child.counts[phase.id] ?? 0}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div key={`priority-${row.id}`} className="flex items-center justify-between px-4 py-2">
                                <div className="flex items-center gap-2">
                                    <PriorityIcon icon={row.icon} title={row.title} />
                                    <span className="text-sm font-medium text-white">{row.title}</span>
                                </div>
                                <span className="text-sm text-gray-200 tabular-nums">{row.counts[phase.id] ?? 0}</span>
                            </div>
                        ),
                    )}
                    <div className="bg-brown-900/60 flex items-center justify-between px-4 py-3">
                        <span className="text-sm font-semibold text-amber-500">Total</span>
                        <span className="text-sm font-semibold text-amber-400 tabular-nums">
                            {phaseTotals[phase.id] ?? 0}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

function TableSkeleton() {
    const fakeRows = Array.from({ length: 6 });
    const fakeCols = Array.from({ length: 4 });

    return (
        <div className="animate-pulse overflow-x-auto rounded border border-amber-600/30">
            <table className="w-full min-w-max border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        <th className="px-4 py-3">
                            <div className="bg-brown-700 h-4 w-32 rounded" />
                        </th>
                        {fakeCols.map((_, i) => (
                            <th key={i} className="px-3 py-3">
                                <div className="bg-brown-700 mx-auto h-4 w-10 rounded" />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-brown-700 divide-y">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-2">
                                <div className="bg-brown-700 h-4 w-28 rounded" />
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

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-3 rounded border border-amber-600/30 py-16 text-center">
            <Icon icon="sack" style="light" className="text-4xl text-amber-500/70" />
            <p className="text-gray-400">No priorities have been assigned yet.</p>
        </div>
    );
}

function flattenRows(rows) {
    return rows.flatMap((row) => (row.kind === "class" ? [row, ...row.children] : [row]));
}

function PrioritiesTable({ phases, table }) {
    const [selectedPhaseId, setSelectedPhaseId] = useLocalStorage("loot.priorities.phase", phases[0]?.id ?? null);

    if (table.length === 0) {
        return <EmptyState />;
    }

    const flatRows = flattenRows(table);

    const columnMax = Object.fromEntries(
        phases.map((phase) => [phase.id, Math.max(0, ...flatRows.map((row) => row.counts[phase.id] ?? 0))]),
    );

    const phaseTotals = Object.fromEntries(
        phases.map((phase) => [phase.id, table.reduce((sum, row) => sum + (row.counts[phase.id] ?? 0), 0)]),
    );

    return (
        <>
            <DesktopTable phases={phases} rows={table} columnMax={columnMax} phaseTotals={phaseTotals} />
            <MobileTable
                phases={phases}
                rows={table}
                selectedPhaseId={selectedPhaseId}
                onSelectPhase={setSelectedPhaseId}
                phaseTotals={phaseTotals}
            />
        </>
    );
}

export default function Priorities({ phases, table }) {
    useLootPrioritiesChannel();

    phases = phases.data ?? phases ?? [];

    return (
        <Master title="Priority stats">
            <SharedHeader backgroundClass="bg-ssctk" title="Highest priority stats" />
            <ToolNav>
                <div className="flex items-center space-x-4">
                    <Link
                        href={route("loot.index")}
                        className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                    >
                        <Icon icon="arrow-left" style="solid" className="mr-2" />
                        <span>Back to loot bias tool</span>
                    </Link>
                </div>
            </ToolNav>
            <PageContainer>
                <p className="mb-8 text-lg">
                    This table shows how loot priorities stack up across the raid. For each class, spec, or role, it
                    looks at the top tier of priorities only, so you can see at a glance where the heaviest demand
                    for an item's drop currently sits.
                </p>
                {phases.length === 0 ? (
                    <EmptyState />
                ) : (
                    <Deferred data="table" fallback={<TableSkeleton />}>
                        <PrioritiesTable phases={phases} table={table} />
                    </Deferred>
                )}
            </PageContainer>
        </Master>
    );
}
