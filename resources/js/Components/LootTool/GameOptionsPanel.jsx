import cn from "@/Helpers/cn";

/**
 * A mock-up of an in-game addon options panel: a dark translucent backdrop with
 * a gold section title on a fading rule, set in Friz Quadrata. `label` names it
 * for assistive technology.
 */
export default function GameOptionsPanel({ title, label, className = "", children }) {
    return (
        <figure
            aria-label={label}
            className={cn(
                "font-friz-quadrata w-full max-w-lg rounded-md border border-amber-800 bg-[#0a0806]/95 px-4 pt-3 pb-4 text-white shadow-lg shadow-black/40 [text-shadow:1px_1px_0_#000]",
                className,
            )}
        >
            <div className="flex items-center gap-2">
                <span aria-hidden="true" className="h-px flex-1 bg-linear-to-r from-transparent to-[#c9a227]/80" />
                <span className="text-[#ffd100]">{title}</span>
            </div>
            <ul className="mt-2 flex flex-col gap-2.5">{children}</ul>
        </figure>
    );
}

/**
 * An unticked checkbox and its label within a `GameOptionsPanel`.
 */
export function GameOptionsCheckbox({ children }) {
    return (
        <li className="flex items-center gap-2">
            <span
                aria-hidden="true"
                className="size-5 shrink-0 rounded-sm border border-slate-500/70 bg-black shadow-[inset_0_1px_3px_rgba(0,0,0,0.9)]"
            />
            <span>{children}</span>
        </li>
    );
}
