import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import { moveByKey } from "@/Components/Datasets/SortableList";
import useAutosave, { AUTOSAVE_HEADERS, autosaveVisit } from "@/Hooks/useAutosave";

/**
 * Whether two key lists hold the same keys in the same order.
 */
function isSameOrder(keys, otherKeys) {
    return keys.length === otherKeys.length && keys.every((itemKey, index) => itemKey === otherKeys[index]);
}

/**
 * A list's order that changes as soon as an item is dropped and saves straight
 * away: `field` holds the item keys in their new order, sent with `data` to
 * `url` as an autosave PATCH. New props become the saved order; the shown
 * order resyncs from them unless a move since the last save is still waiting
 * to go out and the same items are listed, in which case it keeps the move
 * and saves it next. `items` must keep its identity between renders (memoise
 * a filtered list). Returns the ordered items, move(activeKey, overKey), and
 * the save state.
 */
export default function useOrderAutosave({ items, getKey, url, field, data = {}, key }) {
    const [ordered, setOrdered] = useState(items);
    const [isSaving, setIsSaving] = useState(false);
    const [hasSaved, setHasSaved] = useState(false);
    const [error, setError] = useState(null);
    const latest = useRef(items);
    const saved = useRef(items.map(getKey));
    const hasUnsentMove = useRef(false);

    useEffect(() => {
        const serverKeys = items.map(getKey);
        const localKeys = latest.current.map(getKey);
        const isSameSet = isSameOrder([...serverKeys].sort(), [...localKeys].sort());
        saved.current = serverKeys;

        if (hasUnsentMove.current && isSameSet) {
            const itemsByKey = new Map(items.map((item) => [getKey(item), item]));
            latest.current = localKeys.map((itemKey) => itemsByKey.get(itemKey));
        } else {
            hasUnsentMove.current = false;
            latest.current = items;
        }

        setOrdered(latest.current);
    }, [items]);

    async function save() {
        const sent = latest.current.map(getKey);
        setIsSaving(true);

        const result = await autosaveVisit(router, "patch", url, {
            data: { ...data, [field]: sent },
            headers: AUTOSAVE_HEADERS,
        });

        setIsSaving(false);
        setHasSaved(result.ok);
        setError(
            result.ok ? null : (result.errors[field] ?? "The order couldn't be saved. Reload the page and try again."),
        );

        if (result.ok) {
            saved.current = sent;
            hasUnsentMove.current = !isSameOrder(latest.current.map(getKey), sent);
        }

        return result;
    }

    const { schedule } = useAutosave({
        key,
        delay: 0,
        isDirty: () => !isSameOrder(latest.current.map(getKey), saved.current),
        save,
    });

    function move(activeKey, overKey) {
        const next = moveByKey(latest.current, getKey, activeKey, overKey);
        latest.current = next;
        hasUnsentMove.current = true;
        setOrdered(next);
        schedule();
    }

    return { ordered, move, isSaving, hasSaved, error };
}
