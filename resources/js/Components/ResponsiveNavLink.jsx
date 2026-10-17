import { Link } from "@inertiajs/react";
import cn from "@/Helpers/cn";

export default function ResponsiveNavLink({
    href,
    className = "",
    children,
    active = false,
    external = false,
    ...props
}) {
    const Component = external ? "a" : Link;

    return (
        <Component
            href={href}
            className={cn(
                "flex flex-row items-center rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-amber-700 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                className,
            )}
            aria-current={active ? "page" : undefined}
            {...props}
        >
            {children}
        </Component>
    );
}
