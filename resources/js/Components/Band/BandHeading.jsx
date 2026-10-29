import cn from "@/Helpers/cn";

const SIZES = {
    1: "text-5xl md:text-7xl",
    2: "text-4xl md:text-5xl",
    3: "text-2xl md:text-3xl",
};

const PALETTES = {
    amber: {
        eyebrow: "text-amber-300",
        heading: "from-amber-100 to-amber-300",
    },
    camel: {
        eyebrow: "text-camel-300",
        heading: "from-camel-100 to-camel-300",
    },
};

/**
 * A display heading: serif, normal weight, gradient text in the given
 * `palette`, with an optional uppercase eyebrow above it. `className` applies
 * to the wrapper around both; `headingClassName` applies to the heading,
 * where a size overrides the size for `level`.
 */
export default function BandHeading({
    level = 2,
    palette = "amber",
    eyebrow,
    className = "",
    headingClassName = "",
    children,
    ...props
}) {
    const Tag = `h${level}`;
    const colors = PALETTES[palette] ?? PALETTES.amber;

    return (
        <div className={className}>
            {eyebrow && (
                <p className={cn("mb-2 text-sm font-medium tracking-[0.2em] uppercase", colors.eyebrow)}>{eyebrow}</p>
            )}
            <Tag
                className={cn(
                    "bg-linear-to-b bg-clip-text pb-1 font-serif font-normal text-transparent text-shadow-sm",
                    colors.heading,
                    SIZES[level] ?? SIZES[2],
                    headingClassName,
                )}
                {...props}
            >
                {children}
            </Tag>
        </div>
    );
}
