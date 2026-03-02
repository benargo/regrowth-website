import { useState, useEffect, useRef } from "react";
import { Deferred, useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import Modal from "@/Components/Modal";
import InputError from "@/Components/InputError";
import "@/../css/FrizQuadrata.css";

const POLL_INTERVAL_MS = 3000;
const AUTO_DISMISS_MS = 10000;
const FADE_DURATION_MS = 1000;

const STEP_LABELS = ["Upload queued", "Processing GRM roster data", "Preparing addon data", "Complete"];

export default function GRM({ lastUploadTimestamp, memberCount }) {
    const [isDragging, setIsDragging] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [isVisible, setIsVisible] = useState(false);
    const [uploadStatus, setUploadStatus] = useState(null);
    const [countdown, setCountdown] = useState(null);

    const pollIntervalRef = useRef(null);
    const dismissTimerRef = useRef(null);
    const countdownIntervalRef = useRef(null);

    const { data, setData, post, processing, errors } = useForm({
        grm_data: "",
    });

    const clearTimers = () => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }
        if (dismissTimerRef.current) {
            clearTimeout(dismissTimerRef.current);
            dismissTimerRef.current = null;
        }
        if (countdownIntervalRef.current) {
            clearInterval(countdownIntervalRef.current);
            countdownIntervalRef.current = null;
        }
    };

    const beginFadeOut = () => {
        setIsVisible(false);
        setTimeout(() => {
            setShowModal(false);
            setUploadStatus(null);
            setCountdown(null);
        }, FADE_DURATION_MS);
    };

    const startAutoDismiss = () => {
        setCountdown(Math.ceil(AUTO_DISMISS_MS / 1000));

        countdownIntervalRef.current = setInterval(() => {
            setCountdown((prev) => {
                if (prev <= 1) {
                    clearInterval(countdownIntervalRef.current);
                    countdownIntervalRef.current = null;
                    return null;
                }
                return prev - 1;
            });
        }, 1000);

        dismissTimerRef.current = setTimeout(() => {
            beginFadeOut();
        }, AUTO_DISMISS_MS);
    };

    const dismiss = () => {
        clearTimers();
        beginFadeOut();
    };

    const startPolling = () => {
        const poll = async () => {
            try {
                const response = await window.axios.get(route("dashboard.grm-upload.status"));
                const status = response.data;
                setUploadStatus(status);

                if (status.status === "completed" || status.status === "failed") {
                    clearInterval(pollIntervalRef.current);
                    pollIntervalRef.current = null;
                    startAutoDismiss();
                }
            } catch {
                // Silently ignore transient poll errors; keep polling.
            }
        };

        poll();
        pollIntervalRef.current = setInterval(poll, POLL_INTERVAL_MS);
    };

    const startProgressModal = () => {
        clearTimers();
        setUploadStatus({ status: "queued", step: 0, total: 3 });
        setShowModal(true);
        setIsVisible(true);
        startPolling();
    };

    useEffect(() => {
        return () => clearTimers();
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("dashboard.grm-upload.upload"), {
            onSuccess: startProgressModal,
        });
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const files = e.dataTransfer.files;
        if (files.length === 0) {
            return;
        }

        const file = files[0];
        if (file.type !== "text/csv" && !file.name.endsWith(".csv")) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const content = event.target.result;
            setData("grm_data", data.grm_data ? data.grm_data + "\n" + content : content);
        };
        reader.readAsText(file);
    };

    const progressPercent = uploadStatus ? Math.round((uploadStatus.step / (uploadStatus.total || 3)) * 100) : 0;

    const isFinal = uploadStatus?.status === "completed" || uploadStatus?.status === "failed";

    return (
        <Master title="GRM Data Upload">
            <SharedHeader backgroundClass="bg-officer-meeting" title="GRM Data Upload" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <p className="mb-6 text-xl font-bold">Upload your GRM data here.</p>
                    {lastUploadTimestamp ? (
                        <p className="text-md mb-6 text-gray-400">
                            The last GRM data upload was made on {lastUploadTimestamp}
                        </p>
                    ) : (
                        <p className="text-md mb-6 text-gray-400">No previous uploads found.</p>
                    )}
                    <p className="mb-6 text-lg">To export your GRM data, follow these steps:</p>
                    <ol className="mb-6 list-inside list-decimal space-y-2">
                        <li>
                            Open{" "}
                            <span className="inline-block rounded-sm border border-amber-800 bg-brown-800 p-1 font-mono font-bold">
                                /grm export
                            </span>{" "}
                            in-game.
                        </li>
                        <li>
                            Select the <strong>Members</strong> tab.
                        </li>
                        <li>
                            Set the <strong>delimiter</strong> to a comma (
                            <span className="font-mono text-2xl font-bold">,</span>)
                        </li>
                        <li>
                            Make sure the right columns are selected for export. You need to select the following
                            columns:
                            <ul className="my-1 ml-6 list-inside list-disc">
                                <li>Name</li>
                                <li>Rank</li>
                                <li>Level</li>
                                <li>Last Online</li>
                                <li>Main/Alt</li>
                                <li>Player Alts</li>
                            </ul>
                            <p className="italics mt-1 text-gray-400">
                                Any other columns are optional, but ideally you should only select the ones listed
                                above.
                            </p>
                        </li>
                        <li>
                            Make sure <strong>Remove Alt-Code Letters From Names</strong> is{" "}
                            <span className="font-bold uppercase underline">not</span> checked.
                        </li>
                        <li>
                            Make sure <strong>Auto Include Headers</strong>{" "}
                            <span className="font-bold uppercase underline">is</span> checked.
                        </li>
                        <li>
                            Click the
                            <span className="font-friz-quadrata mx-1 inline-block rounded-md border border-gray-600 bg-red-600 px-6 py-2 font-bold text-[#ffff00] shadow-md">
                                Export Selection
                            </span>{" "}
                            button.
                        </li>
                        <li>Copy the exported CSV data, and paste it below.</li>
                        <li>
                            Click the
                            <span className="font-friz-quadrata mx-1 inline-block rounded-md border border-gray-600 bg-red-600 px-6 py-2 font-bold text-[#ffff00] shadow-md">
                                Export Next{" "}
                                <Deferred data="memberCount" fallback={<span className="italics">X</span>}>
                                    {memberCount - 500}
                                </Deferred>
                            </span>
                            button, copy the new data, and paste it below, appending it to the previous data.
                        </li>
                    </ol>

                    <form onSubmit={handleSubmit}>
                        <textarea
                            name="grm_data"
                            rows="10"
                            className={`mb-2 w-full rounded border bg-brown-800 p-4 text-white transition-colors ${
                                isDragging
                                    ? "border-blue-500 bg-brown-700"
                                    : errors.grm_data
                                      ? "border-red-500"
                                      : "border-brown-700"
                            }`}
                            placeholder="Paste your GRM CSV data here, or drag and drop a CSV file."
                            value={data.grm_data}
                            onChange={(e) => setData("grm_data", e.target.value)}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={handleDrop}
                        />
                        <InputError message={errors.grm_data} className="mb-4" />

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? "Uploading..." : "Upload GRM Data"}
                        </button>
                    </form>
                </div>
            </div>

            {showModal && (
                <Modal show={isVisible} maxWidth="lg" closeable={true} onClose={dismiss}>
                    <div className="p-6 text-white">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold">GRM Upload Progress</h2>
                            <button
                                onClick={dismiss}
                                className="text-gray-400 transition-colors hover:text-white"
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
                            <div className="mb-1 flex justify-between text-sm text-gray-400">
                                <span>
                                    {uploadStatus?.step !== undefined && uploadStatus?.total !== undefined
                                        ? (STEP_LABELS[uploadStatus.step] ?? uploadStatus.message)
                                        : "Waiting..."}
                                </span>
                                <span>{progressPercent}%</span>
                            </div>
                            <div className="h-3 w-full overflow-hidden rounded-full bg-brown-700">
                                <div
                                    className={`h-3 rounded-full transition-all duration-500 ${
                                        uploadStatus?.status === "failed" ? "bg-red-500" : "bg-blue-500"
                                    }`}
                                    style={{ width: `${progressPercent}%` }}
                                />
                            </div>
                        </div>

                        {!isFinal && (
                            <div className="flex items-center gap-2 text-sm text-gray-300">
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
                                <span>{uploadStatus?.message ?? "Processing..."}</span>
                            </div>
                        )}

                        {isFinal && (
                            <div className="space-y-3">
                                {uploadStatus.status === "completed" ? (
                                    <div className="flex items-center gap-2 font-semibold text-green-400">
                                        <svg
                                            className="h-5 w-5 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span>{uploadStatus.message}</span>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 font-semibold text-red-400">
                                        <svg
                                            className="h-5 w-5 shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                                            />
                                        </svg>
                                        <span>{uploadStatus.message}</span>
                                    </div>
                                )}

                                {(uploadStatus.processedCount > 0 ||
                                    uploadStatus.skippedCount > 0 ||
                                    uploadStatus.warningCount > 0) && (
                                    <ul className="space-y-1 pl-1 text-sm text-gray-300">
                                        {uploadStatus.processedCount > 0 && (
                                            <li>
                                                <span className="font-semibold text-green-400">
                                                    {uploadStatus.processedCount}
                                                </span>{" "}
                                                characters processed
                                            </li>
                                        )}
                                        {uploadStatus.skippedCount > 0 && (
                                            <li>
                                                <span className="font-semibold text-yellow-400">
                                                    {uploadStatus.skippedCount}
                                                </span>{" "}
                                                skipped (too low level)
                                            </li>
                                        )}
                                        {uploadStatus.warningCount > 0 && (
                                            <li>
                                                <span className="font-semibold text-yellow-400">
                                                    {uploadStatus.warningCount}
                                                </span>{" "}
                                                skipped (API lookup failed)
                                            </li>
                                        )}
                                    </ul>
                                )}

                                {uploadStatus.errors?.length > 0 && (
                                    <div className="mt-2">
                                        <p className="mb-1 text-sm font-semibold text-red-400">
                                            {uploadStatus.errorCount} error{uploadStatus.errorCount !== 1 ? "s" : ""}:
                                        </p>
                                        <ul className="max-h-32 space-y-0.5 overflow-y-auto text-xs text-red-300">
                                            {uploadStatus.errors.slice(0, 10).map((err, i) => (
                                                <li key={i} className="truncate">
                                                    {err}
                                                </li>
                                            ))}
                                            {uploadStatus.errors.length > 10 && (
                                                <li className="text-gray-400">
                                                    ...and {uploadStatus.errors.length - 10} more
                                                </li>
                                            )}
                                        </ul>
                                    </div>
                                )}

                                <div className="flex items-center justify-between pt-2">
                                    <button
                                        onClick={dismiss}
                                        className="rounded bg-blue-600 px-4 py-1.5 text-sm font-bold text-white transition-colors hover:bg-blue-700"
                                    >
                                        Dismiss
                                    </button>
                                    {countdown !== null && (
                                        <span className="text-xs text-gray-400">Closing in {countdown}s</span>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </Modal>
            )}
        </Master>
    );
}
