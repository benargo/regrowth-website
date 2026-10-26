import { useRef } from "react";
import CopyButton from "@/Components/CopyButton";
import { linkButtonClassName } from "@/Components/FormControls";
import cn from "@/Helpers/cn";

/**
 * The size of a string in UTF-8, formatted as bytes or kilobytes.
 */
function formatSize(value) {
    const bytes = new TextEncoder().encode(value).length;

    return bytes < 1024 ? `${bytes} bytes` : `${(bytes / 1024).toFixed(1)} KB`;
}

/**
 * A titled block of export data with a copy button. `copyValue` is what the button copies;
 * `display` is what the block shows (defaults to `copyValue`). Clicking the block selects all
 * of it. `wrap` breaks long unbroken strings, such as Base64. When `copyValue` is empty, the
 * block shows `emptyMessage` and the copy button is hidden.
 */
export default function ExportPanel({
    title,
    description,
    copyValue,
    display = copyValue,
    copyLabel,
    copySuccessMessage,
    wrap = false,
    emptyMessage = "No addon data available.",
}) {
    const dataRef = useRef(null);
    const isEmpty = !copyValue;

    function selectAllContent() {
        if (dataRef.current) {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(dataRef.current);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    return (
        <section className="bg-brown-800/40 overflow-hidden rounded-md border border-amber-700/70">
            <div className="flex flex-col gap-4 border-b border-amber-700/50 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                <div className="min-w-0 flex-1">
                    <h2 className="flex flex-wrap items-baseline gap-x-3 font-serif text-2xl text-white">
                        {title}
                        <span className={cn("font-sans text-sm text-gray-400", isEmpty && "hidden")}>
                            {formatSize(copyValue ?? "")}
                        </span>
                    </h2>
                    <p className="mt-1 text-sm text-gray-300">{description}</p>
                </div>
                <div className={cn("flex-none", isEmpty && "hidden")}>
                    <CopyButton
                        getValue={() => copyValue}
                        label={copyLabel}
                        successMessage={copySuccessMessage}
                        className={cn(linkButtonClassName, "w-full justify-center sm:w-auto")}
                    />
                </div>
            </div>
            <p className={cn("px-5 py-16 text-center text-gray-300", !isEmpty && "hidden")}>{emptyMessage}</p>
            <pre
                ref={dataRef}
                onClick={selectAllContent}
                title="Click to select all"
                className={cn(
                    "max-h-[600px] min-h-64 w-full cursor-pointer overflow-auto bg-black/20 p-4 font-mono text-sm text-gray-100 sm:p-5",
                    wrap && "break-all whitespace-pre-wrap",
                    isEmpty && "hidden",
                )}
            >
                {display}
            </pre>
        </section>
    );
}

/**
 * The pulsing placeholder shown in place of an ExportPanel while its data loads.
 */
export function ExportPanelSkeleton() {
    return (
        <div
            className="bg-brown-800/40 overflow-hidden rounded-md border border-amber-700/70"
            role="status"
            aria-live="polite"
        >
            <div className="flex animate-pulse flex-col gap-4 border-b border-amber-700/50 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                <div className="flex-1 space-y-2">
                    <div className="bg-brown-700 h-6 w-32 rounded" />
                    <div className="bg-brown-700/70 h-4 w-64 max-w-full rounded" />
                </div>
                <div className="bg-brown-700 h-9 w-full rounded-md sm:w-40" />
            </div>
            <div className="min-h-64 space-y-3 bg-black/20 p-4 sm:p-5">
                <div className="animate-pulse space-y-3">
                    <div className="bg-brown-700/70 h-3 w-1/3 rounded" />
                    <div className="bg-brown-700/70 h-3 w-2/3 rounded" />
                    <div className="bg-brown-700/70 h-3 w-1/2 rounded" />
                    <div className="bg-brown-700/70 h-3 w-3/5 rounded" />
                    <div className="bg-brown-700/70 h-3 w-1/4 rounded" />
                </div>
                <p className="pt-4 text-sm text-gray-400">Loading data… this may take a while.</p>
            </div>
        </div>
    );
}
