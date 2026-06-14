import { useEffect, useRef, useState } from "react";

/**
 * Persists a piece of state to localStorage, rehydrating it on mount.
 *
 * Behaves like useState, but the value is written to localStorage under the
 * given key whenever it changes and read back the next time the hook mounts.
 * Reads and writes are guarded so a missing/disabled localStorage (SSR, private
 * browsing) or corrupt JSON falls back to the initial value instead of throwing.
 *
 * @template T
 * @param {string} key - localStorage key to persist under
 * @param {T} initialValue - value to use when nothing is stored (or storage is unavailable)
 * @returns {[T, import("react").Dispatch<import("react").SetStateAction<T>>]}
 */
export default function useLocalStorage(key, initialValue) {
    const [value, setValue] = useState(() => {
        if (typeof window === "undefined") {
            return initialValue;
        }

        try {
            const stored = window.localStorage.getItem(key);
            return stored !== null ? JSON.parse(stored) : initialValue;
        } catch {
            return initialValue;
        }
    });

    // Avoid writing back the freshly-read value on the initial mount.
    const isMounted = useRef(false);

    useEffect(() => {
        if (!isMounted.current) {
            isMounted.current = true;
            return;
        }

        if (typeof window === "undefined") {
            return;
        }

        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // Storage unavailable or quota exceeded: persistence is best-effort.
        }
    }, [key, value]);

    return [value, setValue];
}
