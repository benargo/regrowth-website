import cn from "@/Helpers/cn";
import getSectionPadding from "@/Helpers/getSectionPadding";
import WavyEdge from "./WavyEdge";

/**
 * A full-width Classic band with a centred container. A `light` band has no
 * fill; a `shaded` band has a see-through dark brown fill whose `edge` sides
 * are cut into a wave, with extra padding on those sides.
 */
export default function Band({ tone = "light", edge = "none", className = "", maxWidth = "", children, ...props }) {
    const isShaded = tone === "shaded";
    const hasTopEdge = isShaded && (edge === "top" || edge === "both");
    const hasBottomEdge = isShaded && (edge === "bottom" || edge === "both");
    const padding = getSectionPadding({ hasTopEdge, hasBottomEdge });

    return (
        <section className={cn("relative", className)} {...props}>
            {isShaded && <WavyEdge edge={edge} className="bg-brown-950/60" />}
            <div className={`relative z-10 container mx-auto px-4 ${padding} ${maxWidth}`}>{children}</div>
        </section>
    );
}
