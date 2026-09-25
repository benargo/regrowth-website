import { useEffect, useRef } from "react";
import { Fieldset } from "@headlessui/react";
import { router, usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { useAutosaveQueue } from "@/Hooks/useAutosave";

/** How often the page renews its edit lock, or checks whether it has freed up. */
const EDIT_LOCK_POLL_INTERVAL = 20_000;

/** How long without a key press, click or focus change before the officer counts as idle. */
const EDIT_LOCK_IDLE_AFTER = 5 * 60_000;

const ACTIVITY_EVENTS = ["keydown", "pointerdown", "focusin"];

/**
 * Keep the game version's edit lock while the officer is using the page, and
 * make the page read-only while another officer holds it. The poll is what
 * renews the lock: every edit and setup visit takes or refreshes it on the
 * server. Once the officer is idle, polls say so (X-Edit-Idle) and only
 * check the lock, so a forgotten tab lets it expire for someone else.
 */
export default function EditLockGuard({ canEdit, editor, children }) {
    // Take the stable control callbacks, not the whole context value: that
    // changes on every save status update, and depending on it would re-run
    // the pause/clear effect below (which publishes a new status) forever.
    const { pause, resume, clear } = useAutosaveQueue() ?? {};
    const { errors } = usePage().props;
    const couldEdit = useRef(canEdit);

    const lastActiveAt = useRef(Date.now());

    // The poll is a plain interval rather than usePoll, so each request can
    // decide at send time whether the officer is idle. A hidden tab's timers
    // are throttled to about once a minute, well inside the lock's lifetime.
    useEffect(() => {
        const markActive = () => {
            lastActiveAt.current = Date.now();
        };

        const checkLock = () => {
            const idle = Date.now() - lastActiveAt.current > EDIT_LOCK_IDLE_AFTER;
            router.reload({ only: ["canEdit", "editor"], headers: idle ? { "X-Edit-Idle": "1" } : {} });
        };

        // An officer coming back to the tab sees at once whether someone
        // else has taken over, before they start typing.
        const checkOnReturn = () => {
            if (document.visibilityState === "visible") {
                checkLock();
            }
        };

        ACTIVITY_EVENTS.forEach((type) => window.addEventListener(type, markActive, true));
        document.addEventListener("visibilitychange", checkOnReturn);
        const interval = setInterval(checkLock, EDIT_LOCK_POLL_INTERVAL);

        return () => {
            ACTIVITY_EVENTS.forEach((type) => window.removeEventListener(type, markActive, true));
            document.removeEventListener("visibilitychange", checkOnReturn);
            clearInterval(interval);
        };
    }, []);

    // Stop autosaving while read-only. Pending edits can no longer be saved.
    useEffect(() => {
        if (!pause) {
            return;
        }

        if (canEdit) {
            resume();
        } else {
            pause();
            clear();
        }
    }, [canEdit, pause, resume, clear]);

    // The lock was lost mid-edit: drop queued saves and check the lock now.
    useEffect(() => {
        if (errors?.edit_lock) {
            clear?.();
            router.reload({ only: ["canEdit", "editor"] });
        }
    }, [errors?.edit_lock]);

    // Once the lock frees up, reload without preserved state so every form
    // starts again from the other officer's saved values.
    useEffect(() => {
        if (canEdit && !couldEdit.current) {
            router.visit(window.location.href, { preserveScroll: true });
        }

        couldEdit.current = canEdit;
    }, [canEdit]);

    return (
        <div className="flex flex-col gap-10">
            {!canEdit && (
                <div
                    role="status"
                    className="flex items-center gap-3 border-l-4 border-yellow-500 bg-yellow-100 p-4 text-yellow-800"
                >
                    <Icon icon="lock" style="solid" />
                    <p>
                        {editor ?? "Another officer"} is editing this game version. It will unlock automatically when
                        they finish.
                    </p>
                </div>
            )}
            <Fieldset disabled={!canEdit} className="flex min-w-0 flex-col gap-10">
                {children}
            </Fieldset>
        </div>
    );
}
