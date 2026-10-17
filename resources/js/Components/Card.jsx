import { Link } from "@inertiajs/react";

export default function Card({
    title,
    headerRight,
    className = "",
    children,
    backgroundClass,
    color,
    href,
    onClick,
    selected = false,
    eyebrow,
    heading,
}) {
    if (backgroundClass) {
        const borderColor = color ? `#${color}` : null;
        const Element = href ? Link : "button";

        return (
            <Element
                {...(href ? { href } : { type: "button", onClick })}
                className={`group block w-full overflow-hidden rounded-lg border text-left shadow-lg transition-[border-color,box-shadow,scale] duration-300 hover:scale-[1.03] ${
                    selected ? "border-amber-500 ring-2 ring-amber-500" : "border-amber-600/30"
                } ${className}`}
                style={borderColor ? { "--raid-color": borderColor } : undefined}
                onMouseEnter={(e) => borderColor && (e.currentTarget.style.borderColor = borderColor)}
                onMouseLeave={(e) => borderColor && (e.currentTarget.style.borderColor = "")}
            >
                <div className={`${backgroundClass} relative aspect-video w-full bg-cover bg-center`}>
                    <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/30 to-transparent" />
                    <div className="absolute right-0 bottom-0 left-0 p-4">
                        {eyebrow && (
                            <p className="mb-1 text-xs font-semibold tracking-widest text-amber-400 uppercase">
                                {eyebrow}
                            </p>
                        )}
                        <h3 className="text-xl font-bold text-white drop-shadow-lg">{heading}</h3>
                    </div>
                </div>
            </Element>
        );
    }

    return (
        <div className={`rounded border border-amber-600 ${className}`}>
            {(title || headerRight) && (
                <div className="flex items-center justify-between border-b border-amber-600/40 px-4 py-3">
                    {title && <h3 className="font-semibold text-white">{title}</h3>}
                    {headerRight && <div>{headerRight}</div>}
                </div>
            )}
            <div className="p-4">{children}</div>
        </div>
    );
}
