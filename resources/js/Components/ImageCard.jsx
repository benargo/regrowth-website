import { Link } from "@inertiajs/react";
import cn from "@/Helpers/cn";
import { modelBackground } from "@/Helpers/ModelColor";

/**
 * A full-bleed image card with an optional eyebrow and a heading over a dark gradient.
 * Renders as a `Link` when `href` is set, otherwise as a button calling `onClick`.
 * The `image` sits over a hex `color` (without `#`), at the CSS background
 * position `imagePosition` when given; `backgroundClass` paints the card when
 * there is neither. The colour also tints the border on hover.
 */
export default function ImageCard({
    image,
    imagePosition = null,
    backgroundClass,
    heading,
    eyebrow,
    color,
    href,
    onClick,
    selected = false,
    className = "",
}) {
    const Element = href ? Link : "button";
    const background = modelBackground(color, image, imagePosition);
    const backgroundClassName = image || color ? background.className : backgroundClass;

    return (
        <Element
            {...(href ? { href } : { type: "button", onClick })}
            className={cn(
                "group block w-full overflow-hidden rounded-lg border text-left shadow-lg transition-[border-color,box-shadow,scale] duration-300 hover:scale-[1.03]",
                selected ? "border-amber-500 ring-2 ring-amber-500" : "border-amber-600/30",
                color && "hover:border-(--card-color)",
                className,
            )}
            style={color ? { "--card-color": `#${color}` } : undefined}
        >
            <div
                className={`${backgroundClassName} relative aspect-video w-full bg-cover bg-center`}
                style={background.style}
            >
                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/30 to-transparent" />
                <div className="absolute right-0 bottom-0 left-0 p-4">
                    {eyebrow && (
                        <p className="mb-1 text-xs font-semibold tracking-widest text-amber-400 uppercase">{eyebrow}</p>
                    )}
                    <h3 className="text-xl font-bold text-white drop-shadow-lg">{heading}</h3>
                </div>
            </div>
        </Element>
    );
}
