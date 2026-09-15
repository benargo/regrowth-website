import { useEffect, useState } from "react";
import CountryFlag from "./CountryFlag";

/**
 * A single officer. Renders their in-game character render standing on a
 * shared baseline, with name and nationality beneath.
 *
 * The render is optional. Until `render` arrives from the deferred
 * `officerRenders` prop — and permanently, for an officer with no character
 * row, or if the image itself fails to load — a silhouette is shown instead,
 * styled to match a real render (same footprint, drop shadow and hover
 * lift) so the section looks finished either way. It only pulses while
 * `isLoading` is true, i.e. before the deferred prop has resolved; once
 * resolved, a missing render is a settled state, not a loading one.
 *
 * Blizzard's `main-raw` renders all share one canvas size, but the
 * character's silhouette fills a different fraction of that canvas per
 * race/pose — a Tauren stands far taller in-frame than a Gnome. Sizing the
 * `<img>` itself by CSS height therefore makes officers look inconsistently
 * sized even when `visibleHeight` is the same for both. `render.visibleTop`/
 * `visibleBottom` (fractions of the canvas height measured server-side, see
 * AttachRenderToCharacter) mark where the character actually starts and
 * ends, so the image is scaled by *that* band instead of by canvas height —
 * `imageHeight = visibleHeight / (visibleBottom - visibleTop)` — and shifted
 * up so the excess canvas above the character is what overflows, not the
 * character itself.
 *
 * On desktop the render sits absolutely within a fixed-height wrapper so it
 * can grow taller than its own column, always centred on the column's
 * midline. Spacing between neighbouring columns comes from a margin applied
 * by the parent (`columnStyle`), which — unlike offsetting the image itself
 * by a percentage of its own width — stays consistent regardless of how
 * wide any individual render ends up once scaled by visible character
 * height.
 */
export default function OfficerProfile({
    officer,
    render = null,
    isLoading = false,
    visibleHeight = 224,
    columnStyle,
    zIndexClass = "z-0",
}) {
    const [failed, setFailed] = useState(false);

    // The deferred prop can deliver a render after first paint, and a later
    // visit can replace one that previously 404'd.
    useEffect(() => setFailed(false), [render]);

    const showRender = render !== null && !failed;

    const visibleFraction = showRender ? Math.max(render.visibleBottom - render.visibleTop, 0.01) : 1;
    const imageHeight = visibleHeight / visibleFraction;
    const bottomOffset = showRender ? (1 - render.visibleBottom) * imageHeight : 0;

    return (
        <div className="group flex flex-1 flex-col items-center text-center" style={columnStyle}>
            <div className={`relative w-full ${zIndexClass} hover:z-20`} style={{ height: `${visibleHeight}px` }}>
                {showRender ? (
                    <img
                        src={render.url}
                        alt={`${officer.name}, guild officer`}
                        loading="lazy"
                        onError={() => setFailed(true)}
                        className="absolute left-1/2 w-auto max-w-none -translate-x-1/2 object-contain drop-shadow-[0_8px_20px_rgba(0,0,0,0.55)] transition-transform duration-300 group-hover:-translate-y-1"
                        style={{ height: `${imageHeight}px`, bottom: `${-bottomOffset}px` }}
                    />
                ) : (
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 100 140"
                        preserveAspectRatio="xMidYMax meet"
                        fill="currentColor"
                        className={`text-forever-600 absolute bottom-0 left-1/2 h-full w-auto max-w-none -translate-x-1/2 drop-shadow-[0_8px_20px_rgba(0,0,0,0.55)] transition-transform duration-300 group-hover:-translate-y-1 ${isLoading ? "animate-pulse" : ""}`}
                    >
                        <circle cx="50" cy="42" r="24" />
                        <path d="M50 74c-22 0-38 15-42 38-1 6 3 10 9 10h66c6 0 10-4 9-10-4-23-20-38-42-38z" />
                    </svg>
                )}
            </div>

            {/* The baseline every officer stands on. */}
            <div className="via-primary/50 relative z-10 h-px w-full bg-linear-to-r from-transparent to-transparent" />

            <h3 className="text-camel-200 mt-4 font-serif text-xl font-normal">{officer.name}</h3>

            <p className="text-camel-400 mt-1 flex items-center justify-center gap-1.5 text-sm">
                <CountryFlag countryCode={officer.country_code} />
                <span className="sr-only">{officer.demonym}</span>
            </p>
        </div>
    );
}
