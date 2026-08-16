export const TAILWIND_BREAKPOINTS = ["sm", "md", "lg", "xl", "2xl"];

/**
 * Extracts `{breakpoint}:{propName}` props (e.g. `md:size={24}`) from a
 * spread props object, returning `[breakpoint, propName, value]` triples for
 * any prop whose name is prefixed with a valid Tailwind breakpoint.
 */
export function extractBreakpointProps(props) {
    return Object.entries(props)
        .map(([key, value]) => {
            const [breakpoint, propName] = key.split(":");
            return TAILWIND_BREAKPOINTS.includes(breakpoint) ? [breakpoint, propName, value] : null;
        })
        .filter(Boolean);
}
