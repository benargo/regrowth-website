import cn from "@/Helpers/cn";

/**
 * A mock-up of an in-game tooltip: a translucent blue-black panel with a
 * silver border, set in Friz Quadrata. `label` names it for assistive
 * technology. When `iconUrl` is given, the tooltip is offset to the right and
 * the icon sits in its top-left anchor spot, as item tooltips do in game.
 */
export default function GameTooltip({ label, iconUrl = null, className = "", children }) {
    const panel = (
        <figure
            aria-label={label}
            className={cn(
                "font-friz-quadrata w-full max-w-sm rounded-md border-2 border-slate-400/70 bg-[#05081a]/95 px-3 py-2.5 text-sm leading-snug text-white shadow-lg shadow-black/50 ring-1 ring-black",
                iconUrl ? "max-w-none min-w-0 flex-1" : className,
            )}
        >
            {children}
        </figure>
    );

    if (!iconUrl) {
        return panel;
    }

    return (
        <div className={cn("flex w-full max-w-sm items-start gap-1", className)}>
            <img
                src={iconUrl}
                alt=""
                width={56}
                height={56}
                className="size-11 shrink-0 rounded-sm border border-black shadow-md ring-1 shadow-black/50 ring-slate-400/70"
            />
            {panel}
        </div>
    );
}

/**
 * A heading line within a `GameTooltip`, in the addon's section green.
 */
export function GameTooltipHeading({ className = "", children }) {
    return <p className={cn("mt-2 text-[#1eff00] first:mt-0", className)}>{children}</p>;
}

/**
 * A label and value line within a `GameTooltip`, the value aligned in a column.
 */
export function GameTooltipStat({ label, children }) {
    return (
        <p className="grid grid-cols-[6.5rem_1fr]">
            <span>{label}</span>
            <span>{children}</span>
        </p>
    );
}
