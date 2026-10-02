import { useState, useEffect, useRef, useImperativeHandle, forwardRef } from "react";
import Modal from "@/Components/Modal";
import useGrmUploadChannel from "@/Hooks/useGrmUploadChannel";
import useCountUp from "@/Hooks/useCountUp";

const FADE_DURATION_MS = 1000;

const EMPTY_TALLIES = { processedCount: 0, skippedCount: 0, warningCount: 0, errorCount: 0 };

/**
 * A single animated counter cell in the live tally row.
 */
function TallyStat({ label, value, colorClass }) {
    const animated = useCountUp(value);

    return (
        <div className="bg-ground-800 flex flex-col items-center rounded px-2 py-1.5">
            <span className={`text-lg font-bold ${colorClass}`}>{animated}</span>
            <span className="text-secondary-400 text-center text-xs">{label}</span>
        </div>
    );
}

/**
 * Tracks GRM upload progress broadcasts and renders them in a dismissable modal.
 * Exposes `start()` via ref so the parent can open the modal once the upload is queued.
 */
const UploadProgressModal = forwardRef(function UploadProgressModal(_props, ref) {
    const [showModal, setShowModal] = useState(false);
    const [isVisible, setIsVisible] = useState(false);

    // status: "queued" | "processing" | "completed" | "failed" | "retrying"
    const [status, setStatus] = useState(null);
    const [total, setTotal] = useState(null);
    const [tallies, setTallies] = useState(EMPTY_TALLIES);
    const [currentCharacter, setCurrentCharacter] = useState("");
    const [errors, setErrors] = useState([]);
    const [retry, setRetry] = useState(null);
    const [retryCountdown, setRetryCountdown] = useState(null);

    const retryCountdownRef = useRef(null);

    const clearTimers = () => {
        if (retryCountdownRef.current) {
            clearInterval(retryCountdownRef.current);
            retryCountdownRef.current = null;
        }
    };

    const beginFadeOut = () => {
        setIsVisible(false);
        setTimeout(() => {
            setShowModal(false);
            setStatus(null);
            setTotal(null);
            setTallies(EMPTY_TALLIES);
            setCurrentCharacter("");
            setErrors([]);
            setRetry(null);
            setRetryCountdown(null);
        }, FADE_DURATION_MS);
    };

    const dismiss = () => {
        clearTimers();
        beginFadeOut();
    };

    useEffect(() => {
        return () => clearTimers();
    }, []);

    useGrmUploadChannel({
        onStarted: (payload) => {
            clearTimers();
            // A (re)start re-runs the whole roster — reset to a clean slate.
            setRetry(null);
            setTallies(EMPTY_TALLIES);
            setErrors([]);
            setCurrentCharacter("");
            setTotal(payload.total);
            setStatus("processing");
        },
        onProgressed: (payload) => {
            setTallies({
                processedCount: payload.processedCount,
                skippedCount: payload.skippedCount,
                warningCount: payload.warningCount,
                errorCount: payload.errorCount,
            });
            setTotal(payload.total);
            if (payload.currentCharacter) {
                setCurrentCharacter(payload.currentCharacter);
            }
        },
        onCompleted: (payload) => {
            setTallies({
                processedCount: payload.processedCount,
                skippedCount: payload.skippedCount,
                warningCount: payload.warningCount,
                errorCount: payload.errorCount,
            });
            // Completed broadcasts carry counts only; the error count drives the
            // summary below. Full error detail is sent to the Discord channel.
            setErrors([]);
            setStatus("completed");
        },
        onFailed: (payload) => {
            setErrors(payload.message ? [payload.message] : []);
            setStatus("failed");
        },
        onRetrying: (payload) => {
            clearTimers();
            setStatus("retrying");
            setRetry({ attempt: payload.attempt, maxTries: payload.maxTries });

            let remaining = payload.retryAfter ?? 0;
            setRetryCountdown(remaining);
            if (remaining > 0) {
                retryCountdownRef.current = setInterval(() => {
                    remaining -= 1;
                    setRetryCountdown(remaining > 0 ? remaining : null);
                    if (remaining <= 0) {
                        clearInterval(retryCountdownRef.current);
                        retryCountdownRef.current = null;
                    }
                }, 1000);
            }
        },
    });

    useImperativeHandle(ref, () => ({
        start: () => {
            clearTimers();
            setStatus("queued");
            setTotal(null);
            setTallies(EMPTY_TALLIES);
            setCurrentCharacter("");
            setErrors([]);
            setRetry(null);
            setShowModal(true);
            setIsVisible(true);
        },
    }));

    const processedSoFar = tallies.processedCount + tallies.skippedCount + tallies.warningCount + tallies.errorCount;
    const progressPercent =
        status === "completed"
            ? 100
            : total && total > 0
              ? Math.min(Math.round((processedSoFar / total) * 100), 100)
              : 0;
    const animatedPercent = useCountUp(progressPercent);

    const isQueued = status === "queued";
    const isProcessing = status === "processing";
    const isRetrying = status === "retrying";
    const isCompleted = status === "completed";
    const isFailed = status === "failed";

    const barColor = isFailed ? "bg-red-500" : isCompleted ? "bg-green-500" : "bg-blue-500";

    if (!showModal) {
        return null;
    }

    return (
        <Modal show={isVisible} maxWidth="lg" closeable={true} onClose={dismiss}>
            <div className="p-6 text-white">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-bold">GRM Upload Progress</h2>
                    <button
                        onClick={dismiss}
                        className="text-secondary-400 transition-colors hover:text-white"
                        aria-label="Dismiss"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div className="mb-4">
                    <div className="text-secondary-400 mb-1 flex justify-between text-sm">
                        <span>{total ? `${processedSoFar} of ${total} characters` : "Preparing..."}</span>
                        <span>{animatedPercent}%</span>
                    </div>
                    <div className="bg-ground-700 h-3 w-full overflow-hidden rounded-full">
                        <div
                            className={`h-3 rounded-full transition-all duration-500 ${barColor}`}
                            style={{ width: `${animatedPercent}%` }}
                        />
                    </div>
                </div>

                {(isQueued || isProcessing) && (
                    <div className="space-y-3">
                        <div className="text-secondary-300 flex items-center gap-2 text-sm">
                            <svg
                                className="h-4 w-4 shrink-0 animate-spin text-blue-400"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    className="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    strokeWidth="4"
                                />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            <span>
                                {isQueued
                                    ? "Queued for processing…"
                                    : currentCharacter
                                      ? `Processing ${currentCharacter}…`
                                      : "Processing GRM roster data…"}
                            </span>
                        </div>

                        {isProcessing && (
                            <div className="grid grid-cols-4 gap-2">
                                <TallyStat label="Updated" value={tallies.processedCount} colorClass="text-green-400" />
                                <TallyStat
                                    label="Low level"
                                    value={tallies.skippedCount}
                                    colorClass="text-yellow-400"
                                />
                                <TallyStat
                                    label="Not found"
                                    value={tallies.warningCount}
                                    colorClass="text-yellow-400"
                                />
                                <TallyStat label="Errors" value={tallies.errorCount} colorClass="text-red-400" />
                            </div>
                        )}
                    </div>
                )}

                {isRetrying && (
                    <div className="border-ink-700 bg-ink-600/40 text-heading rounded border p-3 text-sm">
                        <div className="flex items-center gap-2 font-semibold">
                            <svg className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                                />
                            </svg>
                            <span>
                                Attempt {retry?.attempt} of {retry?.maxTries} failed.
                                {retryCountdown !== null ? ` Retrying in ${retryCountdown}s…` : " Retrying…"}
                            </span>
                        </div>
                    </div>
                )}

                {(isCompleted || isFailed) && (
                    <div className="space-y-3">
                        {isCompleted ? (
                            <div className="flex items-center gap-2 font-semibold text-green-400">
                                <svg className="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <span>
                                    {tallies.errorCount > 0 ? "Upload complete (with errors)." : "Upload complete!"}
                                </span>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 font-semibold text-red-400">
                                <svg className="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                                    />
                                </svg>
                                <span>Processing failed.</span>
                            </div>
                        )}

                        {isCompleted &&
                            (tallies.processedCount > 0 ||
                                tallies.skippedCount > 0 ||
                                tallies.warningCount > 0 ||
                                tallies.errorCount > 0) && (
                                <ul className="text-secondary-300 space-y-1 pl-1 text-sm">
                                    {tallies.processedCount > 0 && (
                                        <li>
                                            <span className="font-semibold text-green-400">
                                                {tallies.processedCount}
                                            </span>{" "}
                                            characters processed
                                        </li>
                                    )}
                                    {tallies.skippedCount > 0 && (
                                        <li>
                                            <span className="font-semibold text-yellow-400">
                                                {tallies.skippedCount}
                                            </span>{" "}
                                            skipped (too low level)
                                        </li>
                                    )}
                                    {tallies.warningCount > 0 && (
                                        <li>
                                            <span className="font-semibold text-yellow-400">
                                                {tallies.warningCount}
                                            </span>{" "}
                                            skipped (API lookup failed)
                                        </li>
                                    )}
                                    {tallies.errorCount > 0 && (
                                        <li>
                                            <span className="font-semibold text-red-400">{tallies.errorCount}</span>{" "}
                                            failed (see the officer channel on Discord for details)
                                        </li>
                                    )}
                                </ul>
                            )}

                        {isFailed && errors.length > 0 && (
                            <div className="mt-2">
                                <p className="mb-1 text-sm font-semibold text-red-400">
                                    {errors.length} error{errors.length !== 1 ? "s" : ""}:
                                </p>
                                <ul className="max-h-32 space-y-0.5 overflow-y-auto text-xs text-red-300">
                                    {errors.slice(0, 10).map((err, i) => (
                                        <li key={i} className="truncate">
                                            {err}
                                        </li>
                                    ))}
                                    {errors.length > 10 && (
                                        <li className="text-secondary-400">...and {errors.length - 10} more</li>
                                    )}
                                </ul>
                            </div>
                        )}

                        <div className="pt-2">
                            <button
                                onClick={dismiss}
                                className="rounded bg-blue-600 px-4 py-1.5 text-sm font-bold text-white transition-colors hover:bg-blue-700"
                            >
                                Dismiss
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
});

export default UploadProgressModal;
