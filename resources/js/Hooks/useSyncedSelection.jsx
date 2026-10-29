import { useEffect, useRef } from "react";

/**
 * Merge ids the server has newly linked into a useForm's id-list fields.
 * `serverSelection` maps each field to the ids the server last returned.
 *
 * useForm reads its initial data only once. When a record created further
 * down the page is linked by the server, it would otherwise be missing from
 * the list, and the next save would unlink it. Ticks the officer has not
 * saved yet are kept.
 */
export default function useSyncedSelection(form, serverSelection) {
    const previousSelection = useRef(serverSelection);
    const serverKey = JSON.stringify(serverSelection);

    useEffect(() => {
        const added = Object.fromEntries(
            Object.entries(serverSelection).map(([field, ids]) => [
                field,
                ids.filter((id) => !(previousSelection.current[field] ?? []).includes(id)),
            ]),
        );
        previousSelection.current = serverSelection;

        if (Object.values(added).every((ids) => ids.length === 0)) {
            return;
        }

        form.setData((data) => {
            const merged = { ...data };

            Object.entries(added).forEach(([field, ids]) => {
                merged[field] = [...data[field], ...ids.filter((id) => !data[field].includes(id))];
            });

            return merged;
        });
    }, [serverKey]);
}
