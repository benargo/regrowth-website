import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import ConfirmationModal from "@/Components/ConfirmationModal";
import formatDate from "@/Helpers/FormatDate";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import ReasonLabel from "@/Components/PlannedAbsences/ReasonLabel";
import { usePermission } from "@/Hooks/usePermission";

export default function PlannedAbsenceRow({ absence, showCreatedBy = false, canEdit = false, canDelete = false }) {
    const canEditAny = usePermission("update-planned-absences");
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const deleteAbsence = () => {
        router.delete(route("account.absences.destroy", absence.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
        });
    };

    return (
        <>
            <div className="border-amber-800/50 bg-brown-800/50 flex flex-col gap-2 rounded border px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                <div className="text-amber-300/70 shrink-0 text-sm">
                    <Icon icon="calendar" style="regular" className="mr-1.5 h-4" />
                    {formatDate(absence.start_date).medium}
                    {absence.end_date && (
                        <>
                            <span className="text-gray-400 mx-1">—</span>
                            {formatDate(absence.end_date).medium}
                        </>
                    )}
                </div>

                <div className="text-gray-300 flex flex-1 flex-col gap-1 text-sm">
                    <ReasonLabel reason={absence.reason} className="text-amber-300 font-medium" />
                    {absence.notes && <FormattedMarkdown>{absence.notes}</FormattedMarkdown>}
                </div>

                {showCreatedBy && absence.created_by && absence.created_at && (
                    <div className="text-gray-400 shrink-0 text-xs">
                        Added by {absence.created_by.display_name} on {formatDate(absence.created_at).medium}
                    </div>
                )}

                <div className="flex shrink-0 gap-4">
                    {(canEditAny || canEdit) && (
                        <Link
                            href={route(canEditAny ? "raiding.absences.edit" : "account.absences.edit", absence.id)}
                            className="text-amber-400 hover:text-amber-300 flex items-center text-sm"
                        >
                            <Icon icon="pen" style="regular" className="mr-1.5 h-4" />
                            Edit
                        </Link>
                    )}
                    {canDelete && (
                        <button
                            onClick={() => setConfirmingDelete(true)}
                            className="flex items-center text-sm text-red-400 hover:text-red-300"
                        >
                            <Icon icon="trash" style="regular" className="mr-1.5 h-4" />
                            Delete
                        </button>
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
