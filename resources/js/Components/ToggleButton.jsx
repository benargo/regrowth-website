/**
 * An on/off button that shows `enabledLabel` in green or `disabledLabel` in grey,
 * and "..." while `processing`. Clicking it calls `onToggle`. The button is
 * inert while `processing` or `disabled`.
 */
export default function ToggleButton({
    enabled,
    processing,
    onToggle,
    disabled = false,
    enabledLabel = "Enabled",
    disabledLabel = "Disabled",
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            disabled={disabled || processing}
            aria-pressed={enabled}
            className={`rounded px-3 py-1 text-sm font-medium transition-colors ${
                enabled
                    ? "bg-green-600" + (disabled ? "" : " hover:bg-green-700")
                    : "bg-gray-600" + (disabled ? "" : " hover:bg-gray-700")
            } ${processing ? "cursor-wait opacity-50" : ""} ${disabled ? "cursor-not-allowed opacity-50" : ""}`}
        >
            {processing ? "..." : enabled ? enabledLabel : disabledLabel}
        </button>
    );
}
