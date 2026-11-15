import cn from "@/Helpers/cn";

/**
 * A single headline number with its label. An optional `markerClassName` draws a small coloured bar beside
 * the label so the tile can share an identity colour with marks elsewhere on the page; the label always
 * names the value, so the colour never carries meaning alone.
 */
export default function StatTile({ label, value, markerClassName = null, className = "" }) {
    return (
        <div className={cn("bg-brown-900/60 rounded border border-amber-600/40 px-4 py-3", className)}>
            <dt className="flex items-center gap-2 text-sm text-gray-400">
                <span
                    aria-hidden="true"
                    className={cn("inline-block h-3 w-1 rounded-sm", markerClassName ?? "hidden")}
                />
                {label}
            </dt>
            <dd className="mt-1 text-3xl font-semibold text-white tabular-nums">{value}</dd>
        </div>
    );
}
