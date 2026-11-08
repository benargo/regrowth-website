import cn from "@/Helpers/cn";

const SIZE_CLASSES = {
    sm: "size-4 border text-[0.65rem]",
    md: "size-6 border-2 text-xs",
    lg: "size-14 border-[3px] text-2xl",
};

const SIZE_PIXELS = {
    sm: 16,
    md: 24,
    lg: 56,
};

/**
 * A round disc holding a playable class's icon, or a question mark when the
 * class or its icon is missing. `size` is "sm" (16 px), "md" (24 px) or
 * "lg" (56 px). The icon's alternative text is the class name unless `alt`
 * is given.
 */
export default function ClassToken({ playableClass, size = "md", alt, className = "" }) {
    const iconUrl = playableClass?.icon_url;
    const label = alt ?? playableClass?.name ?? "Unknown class";

    return (
        <span
            className={cn(
                "border-camel-800 bg-forever-900 text-camel-500 inline-grid shrink-0 place-items-center overflow-hidden rounded-full font-serif leading-none shadow-[0_2px_5px_rgba(0,0,0,0.55)]",
                SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
                className,
            )}
        >
            {iconUrl ? (
                <img
                    src={iconUrl}
                    alt={label}
                    width={SIZE_PIXELS[size] ?? SIZE_PIXELS.md}
                    height={SIZE_PIXELS[size] ?? SIZE_PIXELS.md}
                    className="size-full object-cover"
                    loading="lazy"
                />
            ) : (
                <span role="img" aria-label={label || undefined} aria-hidden={label === "" ? true : undefined}>
                    ?
                </span>
            )}
        </span>
    );
}
