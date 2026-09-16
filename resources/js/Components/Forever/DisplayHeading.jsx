const SIZES = {
    1: "text-5xl md:text-7xl",
    2: "text-4xl md:text-5xl",
    3: "text-2xl md:text-3xl",
};

/**
 * Parchment display heading in the Forever style: serif, weight 400, warm
 * camel. Deliberately not bold — light weight is the strongest signal of
 * the World of Warcraft look.
 */
export default function DisplayHeading({ level = 2, eyebrow, className = "", children, ...props }) {
    const Tag = `h${level}`;

    return (
        <div className={className}>
            {eyebrow && <p className="text-camel-400 mb-2 text-sm font-medium tracking-[0.2em] uppercase">{eyebrow}</p>}
            <Tag className={`text-camel-200 font-serif font-normal ${SIZES[level] ?? SIZES[2]}`} {...props}>
                {children}
            </Tag>
        </div>
    );
}
