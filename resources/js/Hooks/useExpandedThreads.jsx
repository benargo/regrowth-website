import { useCallback, useMemo } from "react";
import useLocalStorage from "@/Hooks/useLocalStorage";

/**
 * Remembers which comment threads a user has expanded.
 *
 * The stored set is namespaced by user id so a shared browser never leaks one
 * user's expanded threads to the next person to log in. Guests get a single
 * anonymous namespace.
 *
 * Persistence is best-effort: useLocalStorage swallows storage failures
 * (private browsing, quota pressure), so the worst case is "nothing
 * remembered" rather than a broken page.
 *
 * @param {number|string|null|undefined} userId
 */
export default function useExpandedThreads(userId) {
    const [expandedIds, setExpandedIds] = useLocalStorage(`comments:expanded:${userId ?? "guest"}`, []);

    const expandedSet = useMemo(() => new Set(expandedIds), [expandedIds]);

    const isExpanded = useCallback((id) => expandedSet.has(id), [expandedSet]);

    const expand = useCallback(
        (id) => setExpandedIds((current) => (current.includes(id) ? current : [...current, id])),
        [setExpandedIds],
    );

    const collapse = useCallback(
        (id) => setExpandedIds((current) => current.filter((storedId) => storedId !== id)),
        [setExpandedIds],
    );

    const toggle = useCallback(
        (id) =>
            setExpandedIds((current) =>
                current.includes(id) ? current.filter((storedId) => storedId !== id) : [...current, id],
            ),
        [setExpandedIds],
    );

    return { expandedIds, isExpanded, expand, collapse, toggle };
}
