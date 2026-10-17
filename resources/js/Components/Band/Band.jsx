import cn from "@/Helpers/cn";
import WavyEdge from "./WavyEdge";

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

/**
 * A full-width Classic band with a centred container. A `light` band has no
 * fill; a `shaded` band has a see-through dark brown fill whose `edge` sides
 * are cut into a wave, with extra padding on those sides.
 */
export default function Band({ tone = "light", edge = "none", className = "", maxWidth = "", children, ...props }) {
    const isShaded = tone === "shaded";
    const showTop = isShaded && (edge === "top" || edge === "both");
    const showBottom = isShaded && (edge === "bottom" || edge === "both");
    const padding = getSectionPadding({ showTop, showBottom });

    return (
        <section className={cn("relative", className)} {...props}>
            {isShaded && <WavyEdge edge={edge} className="bg-brown-950/60" />}
            <div className={`relative z-10 container mx-auto px-4 ${padding} ${maxWidth}`}>{children}</div>
        </section>
    );
}
