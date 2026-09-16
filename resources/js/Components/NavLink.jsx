import { Link } from "@inertiajs/react";

export default function NavLink({ href, children, className = "", ...props }) {
    const classes =
        `min-h-9 flex flex-row items-center border-b border-transparent px-4 text-sm font-bold transition-colors hover:bg-surface-raised/80 rounded-sm ${className}`.trim();
    const { external, ...rest } = props;

    if (external) {
        return (
            <a href={href} className={classes} {...rest}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} {...rest}>
            {children}
        </Link>
    );
}
