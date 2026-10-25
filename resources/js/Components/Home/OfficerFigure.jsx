import { useEffect, useState } from "react";
import CountryFlag from "@/Components/CountryFlag";

/** Per-tier name size, and whether the role line shows. */
const TIER_STYLES = {
    council: { name: "text-xl md:text-2xl", showsRole: true },
    officer: { name: "text-base md:text-lg", showsRole: false },
};

/**
 * One officer in a lineup: their image at the row's shared `--figure-h`
 * height (below `sm`, their own height within the lineup's
 * `--single-max-h` and `--single-max-w` caps and the container width) with
 * a contact shadow at their feet, standing on a ledge segment
 * that carries their name, flag and (for the council) role. Segments sit
 * edge to edge so neighbours share one continuous ledge. Shows a silhouette
 * in the same box when the image fails to load.
 */
export default function OfficerFigure({ officer, tier }) {
    const [hasImageFailed, setHasImageFailed] = useState(false);

    useEffect(() => setHasImageFailed(false), [officer.image]);

    const styles = TIER_STYLES[tier] ?? TIER_STYLES.officer;

    return (
        <div
            role="listitem"
            style={{ "--ratio": officer.width / officer.height }}
            className="flex flex-col max-sm:[--figure-h:min(var(--single-max-h),calc(min(var(--single-max-w),100cqw_-_2_*_var(--pad))_/_var(--ratio)))]"
        >
            <div className="relative z-10 flex justify-center px-(--pad)">
                <span
                    aria-hidden="true"
                    className="bg-forever-900/80 absolute bottom-0 left-1/2 h-5 w-[min(80%,12rem)] -translate-x-1/2 translate-y-1/2 rounded-[50%] blur-md"
                />

                {hasImageFailed ? (
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 100 140"
                        preserveAspectRatio="xMidYMax meet"
                        fill="currentColor"
                        style={{ aspectRatio: `${officer.width} / ${officer.height}` }}
                        className="text-forever-600 relative h-(--figure-h)"
                    >
                        <circle cx="50" cy="42" r="24" />
                        <path d="M50 74c-22 0-38 15-42 38-1 6 3 10 9 10h66c6 0 10-4 9-10-4-23-20-38-42-38z" />
                    </svg>
                ) : (
                    <img
                        src={officer.image}
                        alt=""
                        width={officer.width}
                        height={officer.height}
                        loading="lazy"
                        decoding="async"
                        onError={() => setHasImageFailed(true)}
                        className="relative h-(--figure-h) w-auto max-w-none"
                    />
                )}
            </div>

            {/* Ledge: a lit top surface over a darker front face. */}
            <div className="border-forever-400/70 bg-forever-700 -mx-px h-2 border-t" />
            <div className="from-forever-800 via-forever-900/85 flex-1 bg-linear-to-b via-70% to-transparent px-(--pad) pt-3 pb-6 text-center">
                <p
                    className={`text-camel-200 flex items-center justify-center gap-2 font-serif whitespace-nowrap ${styles.name}`}
                >
                    <span translate="no">{officer.name}</span>
                    <CountryFlag countryCode={officer.flag ?? officer.country_code} />
                    <span className="sr-only">{officer.demonym}</span>
                </p>

                {styles.showsRole && officer.role && <p className="text-camel-400 mt-0.5 text-sm">{officer.role}</p>}
            </div>
        </div>
    );
}
