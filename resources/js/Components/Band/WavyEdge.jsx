import cn from "@/Helpers/cn";

const WAVE_PATH =
    "M0 40h1440V18c-38 6-72-4-110-7-44-4-83 9-127 8-52-1-97-14-149-12-45 2-85 15-130 14-51-1-95-16-146-14-43 2-80 14-123 15-49 1-92-12-141-10-38 2-71 12-109 13-72 2-134-12-205-9-38 2-72 9-110 12z";

const WAVE_TOP = `url("data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 40' preserveAspectRatio='none'><path fill='black' d='${WAVE_PATH}'/></svg>`,
)}")`;

const WAVE_BOTTOM = `url("data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 40' preserveAspectRatio='none'><path fill='black' fill-rule='evenodd' d='M0 0h1440v40H0z ${WAVE_PATH}'/></svg>`,
)}")`;

const SOLID = "linear-gradient(#000,#000)";

const MASK_LAYERS = {
    top: `${WAVE_TOP} top / 100% var(--wave-h) no-repeat, ${SOLID} bottom / 100% calc(100% - var(--wave-h) + 1px) no-repeat`,
    bottom: `${SOLID} top / 100% calc(100% - var(--wave-h) + 1px) no-repeat, ${WAVE_BOTTOM} bottom / 100% var(--wave-h) no-repeat`,
    both: `${WAVE_TOP} top / 100% var(--wave-h) no-repeat, ${SOLID} center / 100% calc(100% - 2 * var(--wave-h) + 2px) no-repeat, ${WAVE_BOTTOM} bottom / 100% var(--wave-h) no-repeat`,
};

/**
 * An absolutely positioned fill layer whose top and/or bottom edge is cut
 * into a wave with a CSS mask. The solid mask layer overlaps each wave
 * layer by 1px. The caller passes the fill colour through
 * `className`. Purely decorative.
 */
export default function WavyEdge({ edge, className = "" }) {
    const mask = MASK_LAYERS[edge];

    return (
        <div
            aria-hidden="true"
            className={cn("pointer-events-none absolute inset-0 [--wave-h:1.5rem] md:[--wave-h:2.5rem]", className)}
            style={mask ? { mask, WebkitMask: mask } : undefined}
        />
    );
}
