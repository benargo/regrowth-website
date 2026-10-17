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
                <path
                    fill={TONE_FILL[tone] ?? TONE_FILL.deep}
                    d="M0 40h1440V18c-38 6-72-4-110-7-44-4-83 9-127 8-52-1-97-14-149-12-45 2-85 15-130 14-51-1-95-16-146-14-43 2-80 14-123 15-49 1-92-12-141-10-38 2-71 12-109 13-72 2-134-12-205-9-38 2-72 9-110 12z"
                />
            </svg>
        </div>
    );
}
