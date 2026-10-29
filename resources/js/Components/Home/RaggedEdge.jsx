import { WAVE_PATH } from "@/Components/Band/WavyEdge";

const TONE_FILL = {
    deep: "var(--color-forever-900)",
    mid: "var(--color-forever-800)",
    parchment: "var(--color-forever-700)",
};

/**
 * A torn-parchment divider between page bands, mirroring the section
 * transitions on Blizzard's Forever page. Purely decorative.
 */
export default function RaggedEdge({ position = "bottom", tone = "deep", className = "" }) {
    const flip = position === "top";

    return (
        <div
            aria-hidden="true"
            className={`pointer-events-none absolute inset-x-0 ${flip ? "top-0" : "bottom-0"} h-6 md:h-10 ${className}`}
            style={{ transform: flip ? "scaleY(-1)" : undefined }}
        >
            <svg
                className="h-full w-full"
                viewBox="0 0 1440 40"
                preserveAspectRatio="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path fill={TONE_FILL[tone] ?? TONE_FILL.deep} d={WAVE_PATH} />
            </svg>
        </div>
    );
}
