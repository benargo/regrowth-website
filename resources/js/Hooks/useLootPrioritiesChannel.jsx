import { useEffect, useRef } from "react";
import { router } from "@inertiajs/react";
import { useEcho } from "@laravel/echo-react";

const DEBOUNCE_MS = 600;
const FOCUS_RELOAD_THROTTLE_MS = 5000;

function reloadTable() {
    router.reload({ only: ["table"], preserveState: true, preserveScroll: true });
}

/**
 * Subscribes to the private loot-priorities channel and refetches the
 * aggregate `table` prop whenever anyone's priority edits change it.
 *
 * This is a pure "something changed, go refetch" signal with no payload, so
 * there's nothing to merge client-side - only a debounced reload.
 */
export default function useLootPrioritiesChannel() {
    const timerRef = useRef(null);
    const lastReloadRef = useRef(0);

    useEffect(() => {
        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        };
    }, []);

    useEcho(
        "loot-priorities",
        ".LootPrioritiesChanged",
        () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
            timerRef.current = setTimeout(() => {
                timerRef.current = null;
                lastReloadRef.current = Date.now();
                reloadTable();
            }, DEBOUNCE_MS);
        },
        [],
    );

    useEffect(() => {
        function handleFocusOrVisible() {
            if (document.visibilityState === "hidden") {
                return;
            }
            if (Date.now() - lastReloadRef.current < FOCUS_RELOAD_THROTTLE_MS) {
                return;
            }
            lastReloadRef.current = Date.now();
            reloadTable();
        }

        window.addEventListener("focus", handleFocusOrVisible);
        document.addEventListener("visibilitychange", handleFocusOrVisible);

        return () => {
            window.removeEventListener("focus", handleFocusOrVisible);
            document.removeEventListener("visibilitychange", handleFocusOrVisible);
        };
    }, []);
}
