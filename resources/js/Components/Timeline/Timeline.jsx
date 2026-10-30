import { createContext, useContext } from "react";
import cn from "@/Helpers/cn";
import BandHeading from "@/Components/Band/BandHeading";
import ItemIcon from "@/Components/Items/ItemIcon";

const PALETTES = {
    amber: {
        rail: "border-amber-700/60",
        ring: "ring-brown-950",
        marker: "bg-amber-500",
        latestMarker: "bg-amber-200",
        date: "text-amber-400",
        title: "text-amber-200",
    },
    camel: {
        rail: "border-forever-600",
        ring: "ring-forever-900",
        marker: "bg-camel-400",
        latestMarker: "bg-camel-200",
        date: "text-camel-400",
        title: "text-camel-200",
    },
};

const MARKERS = {
    classic: {
        marker: "bg-[#2b84e0]",
        latestMarker: "bg-[#2b84e0]",
        headingMarker: "border-[#2b84e0]",
        glow: "shadow-[0_0_14px_4px_rgba(43,132,224,0.7)]",
        tint: "to-[#2b84e0]/10",
    },
    anniversary: {
        marker: "bg-[#4ae832]",
        latestMarker: "bg-[#4ae832]",
        headingMarker: "border-[#4ae832]",
        glow: "shadow-[0_0_14px_4px_rgba(74,232,50,0.6)]",
        tint: "to-[#4ae832]/8",
    },
    forever: {
        marker: "bg-[#c69b3a]",
        latestMarker: "bg-[#c69b3a]",
        headingMarker: "border-[#c69b3a]",
        glow: "shadow-[0_0_14px_4px_rgba(198,155,58,0.6)]",
        tint: "to-[#c69b3a]/10",
    },
};

const TimelineContext = createContext({ colors: PALETTES.amber, isCompact: false });

/**
 * An ordered list of `TimelineEntry` items joined by a vertical rail, in the
 * given `palette`. A compact timeline uses smaller type and turns horizontal
 * from `lg` up. `className` applies to the list.
 */
export default function Timeline({ palette = "amber", isCompact = false, className = "", children }) {
    const colors = PALETTES[palette] ?? PALETTES.amber;

    return (
        <TimelineContext.Provider value={{ colors, isCompact }}>
            <ol className={className}>{children}</ol>
        </TimelineContext.Provider>
    );
}

/**
 * A heading that sits on the rail, marked by a hollow ring in the given
 * `marker` colour, dividing the entries that follow into a chapter.
 */
export function TimelineHeading({ id, level = 2, marker, children }) {
    const { colors } = useContext(TimelineContext);
    const markerColors = MARKERS[marker];

    return (
        <li id={id} className={cn("relative border-l pb-8 pl-6 not-first:pt-4", colors.rail)}>
            <div className="relative">
                <span
                    aria-hidden="true"
                    className={cn(
                        "bg-brown-950 absolute top-1/2 -left-8.5 size-5 -translate-y-1/2 rounded-full border-4 ring-4",
                        colors.ring,
                        markerColors?.headingMarker ?? colors.rail,
                    )}
                />
                <BandHeading level={level} id={`${id}-heading`} headingClassName="text-2xl md:text-3xl">
                    {children}
                </BandHeading>
            </div>
        </li>
    );
}

/**
 * A single dated entry, marked by a dot on the rail. The latest entry's
 * marker is larger and brighter. `marker` overrides the palette's dot colour.
 */
export function TimelineEntry({ date, dateTime, title, marker, isLatest = false, children }) {
    const { colors: paletteColors, isCompact } = useContext(TimelineContext);
    const colors = { ...paletteColors, ...MARKERS[marker] };

    return (
        <li
            className={cn(
                "relative flex flex-col border-l pl-6 last:border-l-transparent last:pb-0",
                colors.rail,
                isCompact ? "gap-1 pb-8 lg:border-t lg:border-l-0 lg:pt-6 lg:pr-4 lg:pb-0 lg:pl-0" : "gap-2 pb-10",
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    "absolute rounded-full ring-4",
                    colors.ring,
                    isLatest
                        ? cn("top-1 -left-2 size-4", colors.latestMarker, isCompact && "lg:-top-2 lg:left-0")
                        : cn("top-1.5 -left-1.5 size-3", colors.marker, isCompact && "lg:-top-1.5 lg:left-0"),
                )}
            />
            <time dateTime={dateTime} className={cn("text-sm tabular-nums", colors.date)}>
                {date}
            </time>
            <h3 className={cn("font-serif text-balance", colors.title, isCompact ? "text-lg" : "text-xl")}>{title}</h3>
            {children}
        </li>
    );
}

/**
 * A highlighted entry for a legendary item, marked by a larger glowing dot on
 * the rail. Shows the item's icon and name linked to Wowhead, and an optional
 * sticker beside the text (above it on small screens).
 */
export function TimelineLegendary({ date, dateTime, title, marker, item, sticker, children }) {
    const { colors: paletteColors } = useContext(TimelineContext);
    const colors = { ...paletteColors, ...MARKERS[marker] };
    const itemUrl = `https://www.wowhead.com/${item.domain}/item=${item.id}`;

    return (
        <li
            className={cn(
                "relative flex flex-col gap-2 border-l pb-10 pl-6 last:border-l-transparent last:pb-0",
                colors.rail,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    "absolute top-0.5 -left-2.5 size-5 rounded-full ring-4",
                    colors.ring,
                    colors.marker,
                    colors.glow,
                )}
            />
            <time dateTime={dateTime} className={cn("text-sm tabular-nums", colors.date)}>
                {date}
            </time>
            <div
                className={cn(
                    "border-quality-legendary/50 from-quality-legendary/15 flex flex-col-reverse items-center gap-4 rounded-xl border bg-linear-135 p-4 md:flex-row md:p-5",
                    colors.tint,
                )}
            >
                <div className="flex min-w-0 flex-1 flex-col gap-2 self-stretch">
                    <p className="text-quality-legendary text-xs font-bold tracking-widest uppercase">Legendary</p>
                    <h3 className={cn("font-serif text-xl text-balance", colors.title)}>{title}</h3>
                    <div className="flex items-center gap-2">
                        <span className="shrink-0">
                            <ItemIcon
                                itemId={item.id}
                                itemName={item.name}
                                iconUrl={`https://wow.zamimg.com/images/wow/icons/large/${item.icon}.jpg`}
                                itemQuality="border-quality-legendary"
                                size={8}
                                domain={item.domain}
                            />
                        </span>
                        <a
                            href={itemUrl}
                            data-wowhead={`item=${item.id}&domain=${item.domain}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-quality-legendary font-semibold"
                        >
                            {item.name}
                        </a>
                    </div>
                    {children}
                </div>
                {sticker && (
                    <img
                        src={sticker.src}
                        alt={sticker.alt}
                        width={sticker.width}
                        height={sticker.height}
                        loading="lazy"
                        className={cn(
                            "h-auto shrink-0 object-contain drop-shadow-xl",
                            sticker.isSmall ? "max-h-40 w-32 md:max-h-44 md:w-36" : "max-h-52 w-40 md:max-h-56 md:w-44",
                        )}
                    />
                )}
            </div>
        </li>
    );
}
