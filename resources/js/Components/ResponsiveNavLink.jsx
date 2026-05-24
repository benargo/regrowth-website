import { Link } from "@inertiajs/react";

export default function ResponsiveNavLink({ href, children, ...props }) {
    const classes =
        "flex flex-row items-center rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-amber-700 hover:text-white";
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
