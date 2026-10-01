import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { router } from "@inertiajs/react";

const AutosaveContext = createContext(null);

/**
 * One queue of saves for the whole page. Jobs run one at a time, so an
 * Inertia visit made by one autosave can never cancel another's. A newer job
 * with the same key replaces one that is still waiting: last write wins.
 *
 * Wrap a page in this provider, then use useAutosave() in each field or
 * section, and a prop-less <AutoSaveLabel /> to show the page's save status.
 */
export function AutosaveProvider({ children }) {
    const queue = useRef([]);
    const running = useRef(false);
    const paused = useRef(false);
    const tracked = useRef(0);
    const failedKeys = useRef(new Set());
    const sources = useRef(new Set());
    const idleWaiters = useRef([]);
    const bypassNavigationGuard = useRef(false);
    const [state, setState] = useState({ status: "idle", pending: 0 });

    const pendingCount = () => queue.current.length + (running.current ? 1 : 0) + tracked.current;

    const publish = useCallback((settled = false) => {
        const pending = pendingCount();

        if (pending > 0) {
            setState({ status: "saving", pending });
            return;
        }

        if (settled) {
            setState({ status: failedKeys.current.size > 0 ? "error" : "saved", pending: 0 });
        }

        idleWaiters.current.splice(0).forEach((resolve) => resolve());
    }, []);

    const record = useCallback((key, result) => {
        if (result?.ok === false) {
            failedKeys.current.add(key);
        } else {
            failedKeys.current.delete(key);
        }
    }, []);

    const pump = useCallback(() => {
        if (running.current || paused.current || queue.current.length === 0) {
            return;
        }

        const job = queue.current.shift();
        running.current = true;
        publish();

        Promise.resolve()
            .then(job.run)
            .then(
                (result) => record(job.key, result),
                () => record(job.key, { ok: false }),
            )
            .finally(() => {
                running.current = false;
                publish(true);
            });
    }, [publish, record]);

    // Start the next job only after the finished visit's props have rendered
    // and the sections' effects have run (useSyncedSelection merges newly
    // linked ids then), so a queued job never saves one render behind.
    // publish() always sets a new state object, so this runs after every job.
    useEffect(() => {
        pump();
    }, [state, pump]);

    const enqueue = useCallback(
        (key, run) => {
            const waiting = queue.current.find((job) => job.key === key);

            if (waiting) {
                waiting.run = run;
            } else {
                queue.current.push({ key, run });
            }

            publish();
            pump();
        },
        [publish, pump],
    );

    const track = useCallback(
        (promise, key = "tracked") => {
            tracked.current += 1;
            publish();

            return Promise.resolve(promise)
                .then(
                    (result) => record(key, result),
                    () => record(key, { ok: false }),
                )
                .finally(() => {
                    tracked.current -= 1;
                    publish(true);
                });
        },
        [publish, record],
    );

    const whenIdle = useCallback(
        () => (pendingCount() === 0 ? Promise.resolve() : new Promise((resolve) => idleWaiters.current.push(resolve))),
        [],
    );

    /** Save everything waiting on a timer now, and resolve once the queue drains. */
    const flush = useCallback(() => {
        sources.current.forEach((source) => source.flush());

        return whenIdle();
    }, [whenIdle]);

    const pause = useCallback(() => {
        paused.current = true;
    }, []);

    const resume = useCallback(() => {
        paused.current = false;
        pump();
    }, [pump]);

    /** Drop every job still waiting. The job already running finishes. */
    const clear = useCallback(() => {
        queue.current = [];
        sources.current.forEach((source) => source.cancel());
        publish(true);
    }, [publish]);

    const register = useCallback((source) => {
        sources.current.add(source);

        return () => sources.current.delete(source);
    }, []);

    const hasUnsavedWork = () => pendingCount() > 0 || [...sources.current].some((source) => source.isScheduled());

    // Warn before the tab closes with saves outstanding.
    useEffect(() => {
        function handleBeforeUnload(event) {
            if (hasUnsavedWork()) {
                flush();
                event.preventDefault();
            }
        }

        window.addEventListener("beforeunload", handleBeforeUnload);

        return () => window.removeEventListener("beforeunload", handleBeforeUnload);
    }, [flush]);

    // Hold an Inertia navigation until outstanding saves finish, then resume it.
    // Autosaves themselves are non-GET visits, and polls are async, so neither is held.
    useEffect(
        () =>
            router.on("before", (event) => {
                const { visit } = event.detail;

                if (bypassNavigationGuard.current || visit.method !== "get" || visit.async || visit.prefetch) {
                    return;
                }

                if (paused.current || !hasUnsavedWork()) {
                    return;
                }

                flush().then(() => {
                    bypassNavigationGuard.current = true;

                    try {
                        router.visit(visit.url, { ...visit });
                    } finally {
                        bypassNavigationGuard.current = false;
                    }
                });

                return false;
            }),
        [flush],
    );

    const controls = useMemo(
        () => ({ enqueue, track, flush, pause, resume, clear, register }),
        [enqueue, track, flush, pause, resume, clear, register],
    );

    const value = useMemo(() => ({ ...controls, ...state }), [controls, state]);

    return <AutosaveContext.Provider value={value}>{children}</AutosaveContext.Provider>;
}

/**
 * The nearest provider's queue controls and status, or null outside one.
 */
export function useAutosaveQueue() {
    return useContext(AutosaveContext);
}

/**
 * The page's save status: idle, saving, saved or error, and how many saves
 * are outstanding. Null outside an AutosaveProvider.
 */
export function useAutosaveStatus() {
    const context = useContext(AutosaveContext);

    return context ? { status: context.status, pending: context.pending } : null;
}

/**
 * Save a field or section automatically.
 *
 * - trigger "change": save `delay` ms after the last schedule() call.
 * - trigger "blur": save `delay` ms after focus leaves the element that
 *   containerProps is spread on, unless focus returns first. schedule()
 *   also starts the timer, for checkboxes and selects that Safari doesn't
 *   focus on click.
 * - delay 0 saves straight away.
 *
 * Nothing is saved unless isDirty() is true. save() returns a Promise; one
 * that resolves to { ok: false } marks the save as failed.
 */
export default function useAutosave({ key, isDirty, save, delay = 1000, trigger = "change", enabled = true }) {
    // Only the stable callbacks: the context value itself changes on every
    // save status update, which would rebuild flush and re-register each time.
    const { enqueue, register } = useAutosaveQueue() ?? {};
    const timer = useRef(null);
    const latest = useRef({ isDirty, save });
    latest.current = { isDirty, save };

    const cancel = useCallback(() => {
        clearTimeout(timer.current);
        timer.current = null;
    }, []);

    const flush = useCallback(() => {
        cancel();

        if (!enabled || !latest.current.isDirty()) {
            return;
        }

        const run = () => (latest.current.isDirty() ? latest.current.save() : undefined);

        if (enqueue) {
            enqueue(key, run);
        } else {
            run();
        }
    }, [cancel, enabled, key, enqueue]);

    const schedule = useCallback(() => {
        if (!enabled) {
            return;
        }

        cancel();

        if (delay === 0) {
            flush();
            return;
        }

        timer.current = setTimeout(flush, delay);
    }, [cancel, delay, enabled, flush]);

    useEffect(() => {
        if (!register) {
            return undefined;
        }

        return register({ flush, cancel, isScheduled: () => timer.current !== null });
    }, [register, flush, cancel]);

    useEffect(() => cancel, [cancel]);

    const containerProps =
        enabled && trigger === "blur"
            ? {
                  onFocus: cancel,
                  onBlur: (event) => {
                      if (!event.currentTarget.contains(event.relatedTarget)) {
                          schedule();
                      }
                  },
              }
            : {};

    return { schedule, flush, containerProps };
}

/**
 * Autosave an Inertia useForm, sending only the fields that differ from
 * `saved` (the values the server last returned) with an X-Autosave header.
 * Because `saved` comes from props, anything typed while a save was in
 * flight still differs once the new props arrive, and saves next time.
 */
export function useFormAutosave({ form, url, saved, method = "patch", ...options }) {
    const changes = () => changedFields(form.data, saved);

    return useAutosave({
        ...options,
        isDirty: () => Object.keys(changes()).length > 0,
        save: () => {
            const payload = changes();
            form.transform(() => payload);

            return autosaveVisit(form, method, url, { headers: { "X-Autosave": "1" } });
        },
    });
}

/**
 * The fields of `data` whose values differ from `saved`. Values are compared
 * as strings, and lists regardless of order, so a number typed into an input
 * matches the same number from the server.
 */
export function changedFields(data, saved) {
    const normalise = (value) => (Array.isArray(value) ? [...value].map(String).sort().join(",") : String(value ?? ""));

    return Object.fromEntries(
        Object.entries(data).filter(([field, value]) => normalise(value) !== normalise(saved[field])),
    );
}

/**
 * Make an Inertia visit, through a useForm or the router, as a Promise that
 * resolves to { ok, errors } once the visit finishes.
 */
export function autosaveVisit(formOrRouter, method, url, options = {}) {
    return new Promise((resolve) => {
        let result = { ok: false, errors: {} };
        const args = formOrRouter === router ? [url, options.data ?? {}] : [url];

        formOrRouter[method](...args, {
            preserveScroll: true,
            preserveState: true,
            ...options,
            onSuccess: (...params) => {
                result = { ok: true, errors: {} };
                return options.onSuccess?.(...params);
            },
            onError: (errors) => {
                result = { ok: false, errors };
                return options.onError?.(errors);
            },
            onFinish: (...params) => {
                options.onFinish?.(...params);
                resolve(result);
            },
        });
    });
}
