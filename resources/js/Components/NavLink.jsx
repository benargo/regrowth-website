import { Link, usePage } from "@inertiajs/react";
import cn from "@/Helpers/cn";
import isCurrentUrl from "@/Helpers/isCurrentUrl";

export default function NavLink({ href, children, className = "", active, ...props }) {
    const { url } = usePage();
    const isActive = active ?? isCurrentUrl(href, url);
    const classes = cn(
        "min-h-9 flex flex-row items-center border-b border-transparent px-4 text-sm font-bold transition-colors hover:bg-brown-700/80 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
        className,
    );
    const { external, ...rest } = props;

    if (external) {
        return (
            <a href={href} className={classes} aria-current={isActive ? "page" : undefined} {...rest}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} aria-current={isActive ? "page" : undefined} {...rest}>
            {children}
        </Link>
    );
}
