import cn from "@/Helpers/cn";

/**
 * An inline text link to another website, opened in a new tab. `className`
 * is merged over the base classes.
 */
export default function ExternalLink({ href, className = "", children }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                "text-amber-400 underline decoration-amber-700 underline-offset-4 transition-colors hover:text-amber-300 hover:decoration-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                className,
            )}
        >
            {children}
        </a>
    );
}
