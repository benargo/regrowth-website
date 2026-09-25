import { useEffect, useRef } from "react";

/**
 * Merge ids the server has newly linked into a useForm id-list field.
 *
 * useForm reads its initial data only once. When a record created further
 * down the page is linked to the game version by the server, it would
 * otherwise be missing from the list, and the next save would unlink it.
 * Ticks the officer has not saved yet are kept.
 */
export default function useSyncedSelection(form, field, serverIds) {
    const previousServerIds = useRef(serverIds);
    const serverKey = serverIds.join(",");

    useEffect(() => {
        const added = serverIds.filter((id) => !previousServerIds.current.includes(id));
        previousServerIds.current = serverIds;

        if (added.length === 0) {
            return;
        }

        form.setData((data) => ({
            ...data,
            [field]: [...data[field], ...added.filter((id) => !data[field].includes(id))],
        }));
    }, [serverKey]);
}
