import { useState } from "react";
import { usePage, Link, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import ConfirmationModal from "@/Components/ConfirmationModal";
import formatDate from "@/Helpers/FormatDate";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import { usePermission } from "@/Hooks/usePermission";

function RestoreButton({ userCanRestore = false, absence }) {
    const restoreAbsence = () => {
        userCanRestore &&
            router.post(route("raiding.absences.restore", absence.id), {
                preserveScroll: true,
            });
    };

    if (!userCanRestore) {
        return <div className="flex items-center text-sm text-red-700">Deleted</div>;
    }

    return (
        <button onClick={restoreAbsence} className="flex items-center text-sm text-green-400 hover:text-green-300">
            <Icon icon="trash-restore" style="regular" className="mr-1.5 h-4" />
            Restore
        </button>
    );
}

export default function PlannedAbsenceRow({
    absence,
    showCharacter = false,
    showCreatedBy = false,
    canEdit = false,
    canDelete = false,
}) {
    const { auth } = usePage().props;
    const userCanRestore = auth.user?.admin ?? false; // Only admins can restore absences
    const canManage = usePermission("manage-planned-absences");
    const canDeleteForOthers = usePermission("delete-planned-absences");
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const deleteAbsence = () => {
        const destroyRoute = canDeleteForOthers ? "raiding.absences.destroy" : "account.absences.destroy";

        router.delete(route(destroyRoute, absence.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
        });
    };

    return (
        <>
            <div className="border-ink-800/50 bg-ground-800/50 flex flex-col gap-2 rounded border px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                {showCharacter && (
                    <div className="text-ink-300 shrink-0 font-medium">
                        {absence.character?.name ?? "Unknown Character"}
                    </div>
                )}

                <div className="text-ink-300/70 shrink-0 text-sm">
                    <Icon icon="calendar" style="regular" className="mr-1.5 h-4" />
                    {formatDate(absence.start_date).medium}
                    {absence.end_date && (
                        <>
                            <span className="text-secondary-500 mx-1">—</span>
                            {formatDate(absence.end_date).medium}
                        </>
                    )}
                </div>

                {absence.reason && (
                    <div className="text-secondary-300 flex-1 text-sm">
                        <FormattedMarkdown>{absence.reason}</FormattedMarkdown>
                    </div>
                )}

                {showCreatedBy && absence.created_by && absence.created_at && (
                    <div className="text-secondary-500 shrink-0 text-xs">
                        Added by {absence.created_by.display_name} on {formatDate(absence.created_at).medium}
                    </div>
                )}

                <div className="flex shrink-0 gap-4">
                    {(canManage || canEdit) && (
                        <Link
                            href={route(canManage ? "raiding.absences.edit" : "account.absences.edit", absence.id)}
                            className="text-ink-400 hover:text-ink-300 flex items-center text-sm"
                        >
                            <Icon icon="pen" style="regular" className="mr-1.5 h-4" />
                            Edit
                        </Link>
                    )}
                    {absence.deleted_at ? (
                        <RestoreButton userCanRestore={userCanRestore} absence={absence} />
                    ) : (
                        canDelete && (
                            <button
                                onClick={() => setConfirmingDelete(true)}
                                className="flex items-center text-sm text-red-400 hover:text-red-300"
                            >
                                <Icon icon="trash" style="regular" className="mr-1.5 h-4" />
                                Delete
                            </button>
                        )
                    )}
                </div>
            </div>

            <ConfirmationModal
                show={confirmingDelete}
                onClose={() => setConfirmingDelete(false)}
                onConfirm={deleteAbsence}
                title="Delete Planned Absence"
                confirmLabel="Delete"
                variant="delete"
            >
                Are you sure you want to delete this planned absence? This action cannot be undone.
            </ConfirmationModal>
        </>
    );
}
