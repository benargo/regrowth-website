import cn from "@/Helpers/cn";

/**
 * An on/off switch: a green track when `checked`, grey when not, with a sliding thumb.
 * `label` is its accessible name, and `describedBy` the optional id of an element describing it.
 * Clicking it calls `onChange` with the new state.
 * While `isBusy` it is faded, shows a wait cursor, is marked `aria-disabled` and ignores clicks,
 * keeping keyboard focus.
 */
export default function Switch({ checked, isBusy = false, onChange, label, describedBy, className = "" }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            aria-describedby={describedBy}
            aria-disabled={isBusy}
            onClick={() => {
                if (!isBusy) {
                    onChange(!checked);
                }
            }}
            className={cn(
                "relative inline-flex h-5.5 w-10 shrink-0 cursor-pointer items-center rounded-full p-0.75 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 motion-reduce:transition-none",
                checked ? "bg-green-600" : "bg-stone-600",
                isBusy && "cursor-wait opacity-50",
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    "size-4 rounded-full bg-stone-200 shadow-sm transition-transform duration-150 motion-reduce:transition-none",
                    checked ? "translate-x-4.5" : "translate-x-0",
                )}
            />
        </button>
    );
}
