import { useState } from "react";
import { router } from "@inertiajs/react";

/**
 * The delete-confirmation state for a dataset's Index page.
 * request(record, deleteWarning) opens the confirmation for a record, with an
 * optional sentence naming what the delete also removes; confirm() deletes it
 * through its links.destroy URL, then closes the confirmation whatever the
 * outcome.
 */
export default function useRecordDeletion() {
    const [record, setRecord] = useState(null);
    const [deleteWarning, setDeleteWarning] = useState(null);
    const [deleting, setDeleting] = useState(false);

    function request(nextRecord, warning = null) {
        setRecord(nextRecord);
        setDeleteWarning(warning);
    }

    function cancel() {
        setRecord(null);
        setDeleteWarning(null);
    }

    function confirm() {
        setDeleting(true);
        router.delete(record.links.destroy, {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                cancel();
            },
        });
    }

    return { record, deleteWarning, request, cancel, confirm, deleting };
}
