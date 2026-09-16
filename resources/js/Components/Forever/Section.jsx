import RaggedEdge from "./RaggedEdge";

const TONE_BG = {
    deep: "bg-forever-900",
    mid: "bg-forever-800",
    parchment: "bg-mountains",
};

/**
 * A full-width themed band. Bands alternate tone down the page and are
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
    children,
    ...props
}) {
    const showTop = edge === "top" || edge === "both";
    const showBottom = edge === "bottom" || edge === "both";

    return (
        <section className={`relative ${TONE_BG[tone] ?? TONE_BG.mid} ${className}`} {...props}>
            {showTop && <RaggedEdge position="top" tone={edgeTone} />}
            <div className="relative z-10">{children}</div>
            {showBottom && <RaggedEdge position="bottom" tone={edgeTone} />}
        </section>
    );
}
