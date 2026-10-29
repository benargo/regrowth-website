import getSectionPadding from "@/Helpers/getSectionPadding";
import RaggedEdge from "./RaggedEdge";

const TONE_BG = {
    deep: "bg-forever-900",
    mid: "bg-forever-800",
    parchment: "bg-mountains",
};

/**
 * A full-width homepage band. Bands alternate tone down the page and are
 * separated by torn edges rather than straight rules.
 *
 * `edge` names which sides get a torn divider, and `edgeTone` is the tone
 * of the *adjacent* band the tear reveals.
 */
export default function Section({
    tone = "mid",
    edge = "none",
    edgeTone = "deep",
    className = "",
    maxWidth = "",
    children,
    ...props
}) {
    const hasTopEdge = edge === "top" || edge === "both";
    const hasBottomEdge = edge === "bottom" || edge === "both";
    const padding = getSectionPadding({ hasTopEdge, hasBottomEdge });

    return (
        <section className={`relative ${TONE_BG[tone] ?? TONE_BG.mid} ${className}`} {...props}>
            {hasTopEdge && <RaggedEdge position="top" tone={edgeTone} />}
            <div className={`relative z-10 container mx-auto px-4 ${padding} ${maxWidth}`}>{children}</div>
            {hasBottomEdge && <RaggedEdge position="bottom" tone={edgeTone} />}
        </section>
    );
}
