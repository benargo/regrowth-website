import { useState } from "react";
import { router } from "@inertiajs/react";

/**
 * The delete-confirmation state for a dataset's Index page. request(record)
 * opens the confirmation for a record; confirm() deletes it through its
 * links.destroy URL, then closes the confirmation whatever the outcome.
 */
export default function useRecordDeletion() {
    const [record, setRecord] = useState(null);
    const [deleting, setDeleting] = useState(false);

    function confirm() {
        setDeleting(true);
        router.delete(record.links.destroy, {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setRecord(null);
            },
        });
    }

    return { record, request: setRecord, cancel: () => setRecord(null), confirm, deleting };
}
