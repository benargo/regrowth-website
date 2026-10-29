import OfficerFigure from "@/Components/Home/OfficerFigure";

/**
 * Per-tier bounds for the shared figure height (with a lower cap when the
 * tier stands in two halves), the height and width caps for a figure
 * standing alone below `sm`, the classes that size figures to fit two
 * halves and then, from a breakpoint, one row, and the heading size.
 */
const TIER_STYLES = {
    council: {
        minHeight: "12rem",
        maxHeight: "24rem",
        halfMaxHeight: "16rem",
        singleMaxHeight: "28rem",
        singleMaxWidth: "18rem",
        height: "[--figure-h:var(--fit-half)] lg:[--figure-h:var(--fit-row)]",
        heading: "text-2xl md:text-3xl",
    },
    officer: {
        minHeight: "8rem",
        maxHeight: "15rem",
        halfMaxHeight: "12rem",
        singleMaxHeight: "22rem",
        singleMaxWidth: "16rem",
        height: "[--figure-h:var(--fit-half)] xl:[--figure-h:var(--fit-row)]",
        heading: "text-xl md:text-2xl",
    },
};

/** Sum of the width-to-height ratios of `officers`' images. */
const ratioSumOf = (officers) => officers.reduce((sum, officer) => sum + officer.width / officer.height, 0);

/** Splits `items` into consecutive chunks, the first taking the extra item. */
const splitInto = (items, chunkCount) => {
    const size = Math.ceil(items.length / chunkCount);

    return Array.from({ length: Math.ceil(items.length / size) }, (_, index) =>
        items.slice(index * size, (index + 1) * size),
    );
};

/**
 * One tier of officers standing side by side on a shared ledge. Every
 * figure takes the same height, sized so the row fills the available width
 * within the tier's bounds. When the row doesn't fit it breaks into halves,
 * then each half into pairs, so wrapped rows stay balanced and each keeps
 * an unbroken ledge. Below `sm` each officer stands alone on a full-width
 * ledge, sized individually within the tier's single-figure caps.
 */
export default function OfficerLineup({ title, officers, tier, className = "" }) {
    if (officers.length === 0) {
        return null;
    }

    const styles = TIER_STYLES[tier] ?? TIER_STYLES.officer;
    const halves = splitInto(officers, 2);
    const widestHalf = halves.reduce((widest, half) => (ratioSumOf(half) > ratioSumOf(widest) ? half : widest));
    const fitHeight = (row, maxHeight) =>
        `clamp(${styles.minHeight}, calc((100cqw - ${row.length} * 2 * var(--pad)) / ${ratioSumOf(row)} * 0.9), ${maxHeight})`;

    return (
        <div className={`@container ${className}`}>
            <h3 className={`text-camel-300 mb-6 text-center font-serif font-normal md:mb-8 ${styles.heading}`}>
                {title}
            </h3>

            <div
                role="list"
                style={{
                    "--fit-row": fitHeight(officers, styles.maxHeight),
                    "--fit-half": fitHeight(widestHalf, styles.halfMaxHeight),
                    "--single-max-h": styles.singleMaxHeight,
                    "--single-max-w": styles.singleMaxWidth,
                }}
                className={`flex flex-wrap justify-center gap-y-10 [--pad:0.75rem] lg:[--pad:1.25rem] ${styles.height}`}
            >
                {halves.map((half) => (
                    <div key={half[0].name} role="none" className="flex flex-wrap justify-center gap-y-10 max-sm:w-full">
                        {splitInto(half, Math.ceil(half.length / 2)).map((pair) => (
                            <div
                                key={pair[0].name}
                                role="none"
                                className="flex max-sm:w-full max-sm:flex-col max-sm:gap-y-10"
                            >
                                {pair.map((officer) => (
                                    <OfficerFigure key={officer.name} officer={officer} tier={tier} />
                                ))}
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
