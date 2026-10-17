import { Link, usePage } from "@inertiajs/react";
import cn from "@/Helpers/cn";
import isCurrentUrl from "@/Helpers/isCurrentUrl";

export default function ResponsiveNavLink({ href, className = "", children, active, ...props }) {
    const { url } = usePage();
    const isActive = active ?? isCurrentUrl(href, url);
    const classes = cn(
        "flex flex-row items-center rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-amber-700 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
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
