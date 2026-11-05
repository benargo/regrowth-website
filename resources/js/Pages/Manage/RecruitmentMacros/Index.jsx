import { useState } from "react";
import { router } from "@inertiajs/react";
import AutosavedSortableList from "@/Components/Datasets/AutosavedSortableList";
import IndexLayout from "@/Components/Datasets/IndexLayout";
import EmptyState from "@/Components/EmptyState";
import { FieldError, SecondaryButton } from "@/Components/FormControls";
import MacroRow from "@/Components/RecruitmentMacros/MacroRow";
import SharedHeader from "@/Components/SharedHeader";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import formatDate from "@/Helpers/FormatDate";
import { AutosaveProvider } from "@/Hooks/useAutosave";
import { usePermission } from "@/Hooks/usePermission";
import Master from "@/Layouts/Master";

/**
 * "{n} days left" for a trashed macro.
 *
 * @param {number} days
 * @returns {string}
 */
function daysLeft(days) {
    return `${days} ${days === 1 ? "day" : "days"} left`;
}

export default function Index({ macros, trashed }) {
    const canManage = usePermission("manage-recruitment-macros");
    const [restoringId, setRestoringId] = useState(null);
    const [restoreError, setRestoreError] = useState("");

    function restore(macro) {
        setRestoringId(macro.id);
        setRestoreError("");
        router.post(
            route("management.recruitment-macros.restore", macro.id),
            {},
            {
                preserveScroll: true,
                onError: (errors) => setRestoreError(Object.values(errors)[0] ?? ""),
                onFinish: () => setRestoringId(null),
            },
        );
    }

    return (
        <Master title="Recruitment macros">
            <SharedHeader backgroundClass="bg-officer-meeting" title="Recruitment macros" />

            <AutosaveProvider>
                <IndexLayout
                    intro="Ready-made recruitment messages for in-game chat and Discord. Copy one and paste it where you're recruiting."
                    addHref={canManage ? route("management.recruitment-macros.create") : null}
                    addLabel="New macro"
                    layout="stack"
                    isEmpty={macros.data.length === 0 && trashed.data.length === 0}
                    emptyIcon="bullhorn"
                    emptyMessage="No recruitment macros yet."
                >
                    <section aria-labelledby="macros-heading" className="flex flex-col gap-4">
                        <h2 id="macros-heading" className="font-serif text-2xl text-white">
                            Macros
                        </h2>
                        {macros.data.length === 0 ? (
                            <EmptyState icon="bullhorn" iconStyle="light" message="No live macros." />
                        ) : canManage ? (
                            <AutosavedSortableList
                                items={macros.data}
                                getLabel={(macro) => macro.title}
                                url={route("management.recruitment-macros.reorder")}
                                method="put"
                                field="macro_ids"
                                saveKey="recruitment-macro-order"
                                label="Recruitment macros"
                                className="flex flex-col gap-4"
                                renderItem={(macro, { dragHandle }) => (
                                    <MacroRow macro={macro} canEdit dragHandle={dragHandle} />
                                )}
                            />
                        ) : (
                            <div className="flex flex-col gap-4">
                                {macros.data.map((macro) => (
                                    <MacroRow key={macro.id} macro={macro} canEdit={false} />
                                ))}
                            </div>
                        )}
                    </section>

                    {canManage && (
                        <section aria-labelledby="trashed-heading" className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1">
                                <h2 id="trashed-heading" className="font-serif text-2xl text-white">
                                    Recently deleted
                                </h2>
                                <p className="text-sm text-gray-300">
                                    Deleted macros you can restore stay here for 30 days. Restoring one puts it at the
                                    end of the list.
                                </p>
                            </div>
                            <div role="alert">
                                <FieldError message={restoreError} />
                            </div>
                            {trashed.data.length === 0 ? (
                                <EmptyState
                                    icon="trash-restore"
                                    iconStyle="light"
                                    message="No recently deleted macros."
                                />
                            ) : (
                                <Table>
                                    <TableHead>
                                        <tr>
                                            <TableHeader className="text-left">Macro</TableHeader>
                                            <TableHeader className="text-left">Deleted</TableHeader>
                                            <TableHeader className="text-left">Time left to restore</TableHeader>
                                            <TableHeader className="text-right">
                                                <span className="sr-only">Actions</span>
                                            </TableHeader>
                                        </tr>
                                    </TableHead>
                                    <TableBody>
                                        {trashed.data.map((macro) => (
                                            <TableRow key={macro.id}>
                                                <TableCell className="text-white">{macro.title}</TableCell>
                                                <TableCell className="text-gray-300">
                                                    <time dateTime={macro.deleted_at}>
                                                        {formatDate(macro.deleted_at).medium}
                                                    </time>
                                                </TableCell>
                                                <TableCell className="text-gray-300">
                                                    {daysLeft(macro.meta.days_remaining)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <SecondaryButton
                                                        onClick={() => restore(macro)}
                                                        processing={restoringId === macro.id}
                                                        disabled={restoringId !== null}
                                                    >
                                                        {restoringId === macro.id ? "Restoring…" : "Restore"}
                                                        <span className="sr-only"> {macro.title}</span>
                                                    </SecondaryButton>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </section>
                    )}
                </IndexLayout>
            </AutosaveProvider>
        </Master>
    );
}
