import cn from "@/Helpers/cn";

const SIZES = {
    1: "text-5xl md:text-7xl",
    2: "text-4xl md:text-5xl",
    3: "text-2xl md:text-3xl",
};

/**
 * A Classic display heading: serif, normal weight, amber gradient text, with
 * an optional uppercase eyebrow above it. `className` applies to the wrapper
 * around both; `headingClassName` applies to the heading, where a size
 * overrides the size for `level`.
 */
export default function BandHeading({ level = 2, eyebrow, className = "", headingClassName = "", children, ...props }) {
    const Tag = `h${level}`;

    return (
        <div className={className}>
            {eyebrow && <p className="mb-2 text-sm font-medium tracking-[0.2em] text-amber-300 uppercase">{eyebrow}</p>}
            <Tag
                className={cn(
                    "bg-linear-to-b from-amber-100 to-amber-300 bg-clip-text pb-1 font-serif font-normal text-transparent text-shadow-sm",
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
