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
    const [{ record, deleteWarning }, setPending] = useState({ record: null, deleteWarning: null });
    const [deleting, setDeleting] = useState(false);

    function request(nextRecord, warning = null) {
        setPending({ record: nextRecord, deleteWarning: warning });
    }

    function cancel() {
        setPending({ record: null, deleteWarning: null });
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
