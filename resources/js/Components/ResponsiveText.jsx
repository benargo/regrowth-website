const BREAKPOINT_CLASSES = {
    md: { mobile: "md:hidden", desktop: "hidden md:inline" },
    lg: { mobile: "lg:hidden", desktop: "hidden lg:inline" },
};

/**
 * Swaps between two pieces of copy at a breakpoint, for wording that depends
 * on the layout (e.g. "below" when stacked, "here" when side by side).
 */
export default function ResponsiveText({ mobile, desktop, breakpoint = "lg" }) {
    const classes = BREAKPOINT_CLASSES[breakpoint];

    return (
        <>
            <span className={classes.mobile}>{mobile}</span>
            <span className={classes.desktop}>{desktop}</span>
        </>
    );
}
