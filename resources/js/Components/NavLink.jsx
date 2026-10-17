import { Link, usePage } from "@inertiajs/react";
import cn from "@/Helpers/cn";
import isCurrentUrl from "@/Helpers/isCurrentUrl";

export default function NavLink({ href, children, className = "", active, external = false, ...props }) {
    const { url } = usePage();
    const isActive = active ?? isCurrentUrl(href, url);
    const Component = external ? "a" : Link;

    return (
        <Component
            href={href}
            className={cn(
                "hover:bg-brown-700/80 flex min-h-9 flex-row items-center rounded-sm border-b border-transparent px-4 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                className,
            )}
            aria-current={isActive ? "page" : undefined}
            {...props}
        >
            {children}
        </Component>
    );
}
