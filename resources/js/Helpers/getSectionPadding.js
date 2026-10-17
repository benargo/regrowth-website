/**
 * Vertical padding for a full-width page band, with extra room on each side
 * that carries a decorative edge.
 */
export default function getSectionPadding({ hasTopEdge, hasBottomEdge }) {
    return `${hasTopEdge ? "pt-22 md:pt-28" : "pt-16"} ${hasBottomEdge ? "pb-22 md:pb-28" : "pb-16"}`;
}
