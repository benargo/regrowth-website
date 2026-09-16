import RaggedEdge from "./RaggedEdge";

const TONE_BG = {
    deep: "bg-ground-900",
    mid: "bg-ground-800",
    parchment: "bg-mountains",
};

/**
 * A full-width themed band. Bands alternate tone down the page and are
 * separated by torn edges rather than straight rules.
 *
 * `edge` names which sides get a torn divider, and `edgeTone` is the tone
 * of the *adjacent* band the tear reveals.
 */
const SECTION_PADDING = {
    top: {
        false: "pt-16",
        true: "pt-22 md:pt-28",
    },
    bottom: {
        false: "pb-16",
        true: "pb-22 md:pb-28",
    },
};

function getSectionPadding({ showTop, showBottom }) {
    return `${SECTION_PADDING.top[showTop]} ${SECTION_PADDING.bottom[showBottom]}`;
}

export default function Section({
    tone = "mid",
    edge = "none",
    edgeTone = "deep",
    className = "",
    maxWidth = "",
    children,
    ...props
}) {
    const showTop = edge === "top" || edge === "both";
    const showBottom = edge === "bottom" || edge === "both";
    const padding = getSectionPadding({ showTop, showBottom });

    return (
        <section className={`relative ${TONE_BG[tone] ?? TONE_BG.mid} ${className}`} {...props}>
            {showTop && <RaggedEdge position="top" tone={edgeTone} />}
            <div className={`relative z-10 container mx-auto px-4 ${padding} ${maxWidth}`}>{children}</div>
            {showBottom && <RaggedEdge position="bottom" tone={edgeTone} />}
        </section>
    );
}
