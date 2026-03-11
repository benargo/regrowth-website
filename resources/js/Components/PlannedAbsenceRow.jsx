import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import formatDate from "@/Helpers/FormatDate";
import FormattedMarkdown from "@/Components/FormattedMarkdown";

export default function PlannedAbsenceRow({ absence, showCharacter = false, showCreatedBy = false }) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const deleteAbsence = () => {
        router.delete(route("raids.absences.destroy", absence.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(false),
        });
    };

    return (
        <>
            <div className="flex flex-col gap-2 rounded border border-amber-800/50 bg-brown-800/50 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                {showCharacter && (
                    <div className="shrink-0 font-medium text-amber-300">
                        {absence.character?.name ?? "Unknown Character"}
                    </div>
                )}

                <div className="shrink-0 text-sm text-amber-300/70">
                    <Icon icon="calendar" style="regular" className="mr-1.5 h-4" />
                    {formatDate(absence.start_date).medium}
                    {absence.end_date && (
                        <>
                            <span className="mx-1 text-gray-500">—</span>
                            {formatDate(absence.end_date).medium}
                        </>
                    )}
                </div>

                {absence.reason && (
                    <div className="flex-1 text-sm text-gray-300">
                        <FormattedMarkdown>{absence.reason}</FormattedMarkdown>
                    </div>
                )}

                {showCreatedBy && absence.created_by && absence.created_at && (
                    <div className="shrink-0 text-xs text-gray-500">Added by {absence.created_by.display_name} on {formatDate(absence.created_at).medium}</div>
                )}

                <div className="flex shrink-0 gap-4">
                    <Link
                        href={route("raids.absences.edit", absence.id)}
                        className="flex items-center text-sm text-amber-400 hover:text-amber-300"
                    >
                        <Icon icon="pen" style="regular" className="mr-1.5 h-4" />
                        Edit
                    </Link>
                    <button
                        onClick={() => setConfirmingDelete(true)}
                        className="flex items-center text-sm text-red-400 hover:text-red-300"
                    >
                        <Icon icon="trash" style="regular" className="mr-1.5 h-4" />
                        Delete
                    </button>
                </div>
            </div>

            <Modal show={confirmingDelete} onClose={() => setConfirmingDelete(false)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-white">Delete Planned Absence</h2>
                    <p className="mt-2 text-sm text-gray-400">
                        Are you sure you want to delete this planned absence? This action cannot be undone.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            onClick={() => setConfirmingDelete(false)}
                            className="rounded px-4 py-2 text-sm text-gray-300 hover:text-white"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={deleteAbsence}
                            className="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
