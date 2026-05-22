import { Link } from "@inertiajs/react";

export default function NavLink({ href, children, ...props }) {
    const classes =
        "flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white";
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
