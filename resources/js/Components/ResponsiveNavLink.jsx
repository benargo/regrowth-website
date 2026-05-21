import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ href, children, ...props }) {
    const classes = 'flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white';

    if (props.external) {
        const { external, ...rest } = props;
        return <a href={href} className={classes} {...rest}>{children}</a>;
    }

    return <Link href={href} className={classes} {...props}>{children}</Link>;
}
